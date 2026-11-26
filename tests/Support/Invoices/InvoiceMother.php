<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Invoices\Domain\ValueObjects\ProductLine;

final class InvoiceMother
{
    public static function customer(): Customer
    {
        return new Customer(name: 'Jane Doe', email: 'jane@example.com');
    }

    /** @return list<ProductLine> */
    public static function lines(): array
    {
        return [new ProductLine(productName: 'Consulting', quantity: 3, unitPrice: 1500)];
    }

    /** @param list<ProductLine>|null $lines */
    public static function draft(?array $lines = null): Invoice
    {
        return Invoice::draft(
            id: InvoiceId::generate(),
            customer: self::customer(),
            productLines: $lines ?? self::lines(),
        );
    }

    /** @param list<ProductLine>|null $lines */
    public static function restored(InvoiceStatus $status, ?array $lines = null): Invoice
    {
        return Invoice::restore(
            id: InvoiceId::generate(),
            customer: self::customer(),
            productLines: $lines ?? self::lines(),
            status: $status,
        );
    }
}
