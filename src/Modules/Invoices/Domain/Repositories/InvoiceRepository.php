<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Repositories;

use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Shared\Domain\Exceptions\Conflict;

interface InvoiceRepository
{
    /** @throws InvoiceNotFound */
    public function load(InvoiceId $id): Invoice;

    /**
     * Inserts a new invoice or updates one loaded from this repository.
     *
     * @throws Conflict when the id already exists or the invoice changed in the meantime
     */
    public function save(Invoice $invoice): void;
}
