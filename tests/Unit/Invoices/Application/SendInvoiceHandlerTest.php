<?php

declare(strict_types=1);

use Modules\Invoices\Application\Commands\Handlers\SendInvoiceHandler;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Ports\InvoiceDelivery;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Shared\Domain\BusinessRuleViolation;
use Modules\Shared\Domain\Exceptions\Conflict;
use Tests\Support\Invoices\InvoiceMother;

// The rollback after a failed notification needs a real transaction, see SendInvoiceTest.

/** @return array{SendInvoiceHandler, ArrayObject<int, string>} */
function sendInvoiceHandler(Invoice $invoice, ?Conflict $saveConflict = null): array
{
    $effects = new ArrayObject;
    $handler = new SendInvoiceHandler(
        invoices: new readonly class($invoice, $effects, $saveConflict) implements InvoiceRepository
        {
            public function __construct(private Invoice $invoice, private ArrayObject $effects, private ?Conflict $conflict) {}

            public function load(InvoiceId $id): Invoice
            {
                return $this->invoice;
            }

            public function save(Invoice $invoice): void
            {
                $this->effects[] = 'save:'.$invoice->status->value;
                if ($this->conflict !== null) {
                    throw $this->conflict;
                }
            }
        },
        delivery: new readonly class($effects) implements InvoiceDelivery
        {
            public function __construct(private ArrayObject $effects) {}

            public function send(Invoice $invoice): void
            {
                $this->effects[] = 'deliver';
            }
        },
    );

    return [$handler, $effects];
}

it('saves the sending status before requesting the notification', function (): void {
    $invoice = InvoiceMother::draft();
    [$handler, $effects] = sendInvoiceHandler($invoice);

    $handler(new SendInvoiceCommand(invoiceId: $invoice->id));

    expect($effects->getArrayCopy())->toBe(['save:sending', 'deliver']);
});

it('sends no e-mail when saving conflicts', function (): void {
    $invoice = InvoiceMother::draft();
    [$handler, $effects] = sendInvoiceHandler($invoice, new Conflict('Changed concurrently.', 'INVOICE_CHANGED_CONCURRENTLY'));

    expect(fn () => $handler(new SendInvoiceCommand(invoiceId: $invoice->id)))->toThrow(Conflict::class)
        ->and($effects->getArrayCopy())->toBe(['save:sending']);
});

it('neither saves nor e-mails when the invoice refuses to be sent', function (): void {
    $invoice = InvoiceMother::draft(lines: []);
    [$handler, $effects] = sendInvoiceHandler($invoice);

    expect(fn () => $handler(new SendInvoiceCommand(invoiceId: $invoice->id)))->toThrow(BusinessRuleViolation::class)
        ->and($effects->getArrayCopy())->toBe([])
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);
});
