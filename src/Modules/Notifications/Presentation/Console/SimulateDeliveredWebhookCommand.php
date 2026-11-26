<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;
use Modules\Notifications\Presentation\Http\Middleware\VerifyWebhookSignature;
use Ramsey\Uuid\Uuid;

/**
 * Local stand-in for the provider (DummyDriver never calls the webhook). Sends a
 * signed "delivered" request through the HTTP kernel, so the middleware and the
 * listener run as usual.
 */
final class SimulateDeliveredWebhookCommand extends Command
{
    protected $signature = 'notifications:simulate-delivered {reference : The notification reference, e.g. an invoice id}';

    protected $description = 'Act as the notification provider and report a delivery through the signed webhook';

    public function handle(Kernel $kernel, WebhookSignature $signature): int
    {
        $reference = (string) $this->argument('reference');
        if (! Uuid::isValid($reference)) {
            $this->error('The reference must be a UUID.');

            return self::INVALID;
        }

        $request = Request::create(
            uri: route('notification.hook', ['action' => 'delivered', 'reference' => $reference], absolute: false),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', VerifyWebhookSignature::HEADER)) => $signature->header(
                    action: 'delivered',
                    reference: $reference,
                    timestamp: Date::now()->getTimestamp(),
                ),
            ],
        );
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->line(trim("{$response->getStatusCode()} {$response->getContent()}"));

        return $response->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
