<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final readonly class CreateInvoiceCommand
{
    /** @param list<ProductLineData> $productLines */
    public function __construct(
        public InvoiceId $invoiceId,
        public string $customerName,
        public string $customerEmail,
        public array $productLines = [],
    ) {}
}
