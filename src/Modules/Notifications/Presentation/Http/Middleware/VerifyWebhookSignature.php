<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;
use Symfony\Component\HttpFoundation\Response;

final readonly class VerifyWebhookSignature
{
    public const string HEADER = 'X-Notification-Signature';

    public function __construct(
        private WebhookSignature $signature,
        private Application $app,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header(self::HEADER);
        $signed = $this->signature->verify(
            header: is_string($header) ? $header : null,
            action: (string) $request->route('action'),
            reference: (string) $request->route('reference'),
            now: Date::now()->getTimestamp(),
        );
        if (! $signed) {
            $error = [
                'code' => 'WEBHOOK_SIGNATURE_INVALID',
                'message' => 'The webhook signature is missing, invalid or expired.',
            ];
            // for anyone calling the hook by hand locally
            if ($this->app->environment('local')) {
                $error['hint'] = 'Locally, report a delivery with: php artisan notifications:simulate-delivered <reference>';
            }

            return new JsonResponse(['error' => $error], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
