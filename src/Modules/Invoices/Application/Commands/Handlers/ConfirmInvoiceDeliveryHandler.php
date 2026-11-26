<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands\Handlers;

use Modules\Invoices\Application\Commands\ConfirmInvoiceDeliveryCommand;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

final readonly class ConfirmInvoiceDeliveryHandler
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function __invoke(ConfirmInvoiceDeliveryCommand $command): void
    {
        $invoice = $this->invoices->load(id: $command->invoiceId);
        $invoice->confirmDelivery();
        // nothing is written if the delivery was already confirmed
        $this->invoices->save(invoice: $invoice);
    }
}
