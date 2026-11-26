<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Illuminate\Testing\TestResponse;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;
use Modules\Notifications\Presentation\Http\Middleware\VerifyWebhookSignature;
use Tests\TestCase;

/** The real app, only NotificationFacadeInterface is faked. */
abstract class InvoicesHttpTestCase extends TestCase
{
    protected RecordingNotificationFacade $notifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifications = new RecordingNotificationFacade;
        // NotificationServiceProvider is deferred. If it loads later (the webhook needs
        // WebhookSignature), it binds the real facade again and the fake is gone.
        $this->app->loadDeferredProviders();
        $this->app->instance(NotificationFacadeInterface::class, $this->notifications);
    }

    /**
     * Pass null to drop a field.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function invoicePayload(array $overrides = []): array
    {
        return array_filter(array_replace([
            'customerName' => 'Jane Doe',
            'customerEmail' => 'jane@example.com',
            'productLines' => [
                ['productName' => 'Consulting', 'quantity' => 3, 'unitPrice' => 1500],
                ['productName' => 'Licence', 'quantity' => 1, 'unitPrice' => 9900],
            ],
        ], $overrides), static fn (mixed $value): bool => $value !== null);
    }

    protected function createInvoice(array $overrides = []): string
    {
        return $this->postJson(route('invoices.store'), $this->invoicePayload($overrides))
            ->assertCreated()
            ->json('data.id');
    }

    protected function sendInvoice(string $invoiceId): TestResponse
    {
        return $this->postJson(route('invoices.send', ['invoiceId' => $invoiceId]));
    }

    /** Calls the webhook the way the provider would. */
    protected function reportDelivered(string $reference, bool $signed = true): TestResponse
    {
        $headers = $signed ? [VerifyWebhookSignature::HEADER => $this->app->make(WebhookSignature::class)
            ->header(action: 'delivered', reference: $reference, timestamp: now()->getTimestamp())] : [];

        return $this->getJson(route('notification.hook', ['action' => 'delivered', 'reference' => $reference]), $headers);
    }

    protected function statusOf(string $invoiceId): string
    {
        return $this->getJson(route('invoices.show', ['invoiceId' => $invoiceId]))->assertOk()->json('data.status');
    }
}
