<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Shared\Domain\Exceptions\NotFound;

final class InvoiceNotFound extends NotFound
{
    public static function withId(InvoiceId $id): self
    {
        return new self(message: "Invoice {$id} not found.", errorCode: 'INVOICE_NOT_FOUND');
    }
}
