<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands\Handlers;

use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\ProductLine;

final readonly class CreateInvoiceHandler
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function __invoke(CreateInvoiceCommand $command): void
    {
        $this->invoices->save(invoice: Invoice::draft(
            id: $command->invoiceId,
            customer: new Customer(name: $command->customerName, email: $command->customerEmail),
            productLines: array_map(
                static fn (ProductLineData $line): ProductLine => new ProductLine(
                    productName: $line->productName,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                ),
                $command->productLines,
            ),
        ));
    }
}
