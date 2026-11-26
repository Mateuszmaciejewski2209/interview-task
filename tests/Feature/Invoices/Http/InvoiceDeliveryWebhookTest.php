<?php

declare(strict_types=1);

use Modules\Invoices\Domain\ValueObjects\InvoiceId;

// Notifications webhook -> WebhookDeliveredEvent -> Invoices listener.

it('goes from draft to sent-to-client after send and a delivery report', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();
    $reference = $this->notifications->sent[0]->resourceId->toString();

    $this->reportDelivered($reference)->assertNoContent();

    expect($this->statusOf($id))->toBe('sent-to-client');
});

it('acknowledges a repeated delivery report without a change', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();
    $this->reportDelivered($id)->assertNoContent();

    $this->reportDelivered($id)->assertNoContent();

    expect($this->statusOf($id))->toBe('sent-to-client');
});

it('refuses a delivery report for a draft, so the provider retries', function (): void {
    $id = $this->createInvoice();

    $this->reportDelivered($id)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_MUST_BE_SENDING');

    expect($this->statusOf($id))->toBe('draft');
});

it('rejects a forged, unsigned delivery report without a change', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();

    $this->reportDelivered($id, signed: false)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');

    expect($this->statusOf($id))->toBe('sending');
});

it('confirms the delivery through the provider simulator command', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();

    $this->artisan('notifications:simulate-delivered', ['reference' => $id])
        ->expectsOutput('204')
        ->assertSuccessful();

    expect($this->statusOf($id))->toBe('sent-to-client');
});

it('reports a refused confirmation from the simulator command as a failure', function (): void {
    $id = $this->createInvoice();

    $this->artisan('notifications:simulate-delivered', ['reference' => $id])->assertFailed();

    expect($this->statusOf($id))->toBe('draft');
});

it('ignores a delivery report for another resource', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();

    $this->reportDelivered(InvoiceId::generate()->value)->assertNoContent();

    expect($this->statusOf($id))->toBe('sending');
});
