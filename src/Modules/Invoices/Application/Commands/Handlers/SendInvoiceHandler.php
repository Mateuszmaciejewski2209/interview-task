<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands\Handlers;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Ports\InvoiceDelivery;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

/**
 * Save before notify: a concurrent send fails on the conditional save, before any
 * e-mail goes out. If notify throws, the transaction rolls back and the invoice
 * stays a draft.
 *
 * Known gap: if the e-mail goes out and then the commit fails, the invoice is still
 * a draft and sending it again e-mails the customer twice. See the module README.
 */
final readonly class SendInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceDelivery $delivery,
    ) {}

    public function __invoke(SendInvoiceCommand $command): void
    {
        $invoice = $this->invoices->load(id: $command->invoiceId);
        $invoice->send();
        $this->invoices->save(invoice: $invoice);

        $this->delivery->send(invoice: $invoice);
    }
}
