<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries\Handlers;

use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Queries\GetInvoiceQuery;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

/** Reads through the repository for now. A list endpoint would get its own read model. */
final readonly class GetInvoiceQueryHandler
{
    public function __construct(private InvoiceRepository $invoices) {}

    /** @throws InvoiceNotFound */
    public function __invoke(GetInvoiceQuery $query): InvoiceView
    {
        return InvoiceView::fromInvoice(invoice: $this->invoices->load(id: $query->invoiceId));
    }
}
