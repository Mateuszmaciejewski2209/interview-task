<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final readonly class ConfirmInvoiceDeliveryCommand
{
    public function __construct(
        public InvoiceId $invoiceId,
    ) {}
}
