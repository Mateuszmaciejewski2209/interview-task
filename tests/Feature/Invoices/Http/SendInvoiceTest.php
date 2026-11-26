<?php

declare(strict_types=1);

use Modules\Invoices\Domain\ValueObjects\InvoiceId;

it('e-mails the customer once and marks the invoice as sending', function (): void {
    $id = $this->createInvoice();

    $this->sendInvoice($id)->assertNoContent();

    expect($this->statusOf($id))->toBe('sending')
        ->and($this->notifications->sent)->toHaveCount(1);
    $notification = $this->notifications->sent[0];
    expect($notification->resourceId->toString())->toBe($id)
        ->and($notification->toEmail)->toBe('jane@example.com')
        ->and($notification->subject)->toContain($id)
        ->and($notification->message)->toContain('14400');
});

it('sends an invoice only once', function (): void {
    $id = $this->createInvoice();
    $this->sendInvoice($id)->assertNoContent();

    $this->sendInvoice($id)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_MUST_BE_DRAFT');

    expect($this->statusOf($id))->toBe('sending')
        ->and($this->notifications->sent)->toHaveCount(1);
});

it('does not send an invoice without product lines', function (): void {
    $id = $this->createInvoice(['productLines' => []]);

    $this->sendInvoice($id)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_MUST_HAVE_PRODUCT_LINES');

    expect($this->statusOf($id))->toBe('draft')
        ->and($this->notifications->sent)->toBeEmpty();
});

it('rolls back to a draft that can be sent again when the notification is refused', function (): void {
    $id = $this->createInvoice();
    $this->notifications->failWith(new RuntimeException('SMTP connection refused'));

    $this->sendInvoice($id)
        ->assertServiceUnavailable()
        ->assertJsonPath('error.code', 'INVOICE_DELIVERY_UNAVAILABLE');
    expect($this->statusOf($id))->toBe('draft');

    $this->notifications->recover();
    $this->sendInvoice($id)->assertNoContent();
    expect($this->statusOf($id))->toBe('sending')
        ->and($this->notifications->sent)->toHaveCount(1);
});

it('reports an unknown invoice as not found', function (): void {
    $this->sendInvoice(InvoiceId::generate()->value)
        ->assertNotFound()
        ->assertJsonPath('error.code', 'INVOICE_NOT_FOUND');

    expect($this->notifications->sent)->toBeEmpty();
});
