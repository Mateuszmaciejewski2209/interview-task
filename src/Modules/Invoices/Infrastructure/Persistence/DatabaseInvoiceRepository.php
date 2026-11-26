<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\InvoiceModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\ProductLineModel;
use Modules\Shared\Domain\BusinessRuleViolation;
use Modules\Shared\Domain\Exceptions\Conflict;
use UnexpectedValueException;
use WeakMap;

/**
 * An update is a compare-and-set on the status the invoice was loaded with, so a
 * stale write fails with a Conflict on any driver. lockForUpdate() does nothing on
 * SQLite, there BEGIN IMMEDIATE makes the second request wait (config/database.php).
 */
final class DatabaseInvoiceRepository implements InvoiceRepository
{
    /** @var WeakMap<Invoice, InvoiceStatus> status as loaded from the database */
    private readonly WeakMap $stored;

    public function __construct()
    {
        $this->stored = new WeakMap;
    }

    public function load(InvoiceId $id): Invoice
    {
        $query = InvoiceModel::query()->with('productLines');
        if ($query->getConnection()->transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $invoice = $this->toDomain(model: $query->find($id->value) ?? throw InvoiceNotFound::withId($id));
        $this->stored[$invoice] = $invoice->status;

        return $invoice;
    }

    public function save(Invoice $invoice): void
    {
        // not loaded here = new invoice, always an insert (a repeated create can't overwrite anything)
        $storedStatus = $this->stored[$invoice] ?? null;
        if ($storedStatus === null) {
            $this->insert(invoice: $invoice);
        } elseif ($storedStatus !== $invoice->status) {
            $this->updateStatus(invoice: $invoice, expected: $storedStatus);
        }
        $this->stored[$invoice] = $invoice->status;
    }

    private function insert(Invoice $invoice): void
    {
        try {
            InvoiceModel::query()->getConnection()->transaction(static function () use ($invoice): void {
                InvoiceModel::query()->create([
                    'id' => $invoice->id->value,
                    'customer_name' => $invoice->customer->name,
                    'customer_email' => $invoice->customer->email,
                    'status' => $invoice->status->value,
                ]);
                foreach ($invoice->productLines as $position => $line) {
                    ProductLineModel::query()->create([
                        'invoice_id' => $invoice->id->value,
                        'position' => $position,
                        'name' => $line->productName,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new Conflict(
                message: 'An invoice with this identity already exists.',
                errorCode: 'INVOICE_ALREADY_EXISTS',
                previous: $exception,
            );
        }
    }

    /** Only the status can change after creation. */
    private function updateStatus(Invoice $invoice, InvoiceStatus $expected): void
    {
        $updated = InvoiceModel::query()
            ->whereKey($invoice->id->value)
            ->where('status', $expected->value)
            ->update(['status' => $invoice->status->value]);
        if ($updated !== 1) {
            throw new Conflict(
                message: 'The invoice was changed by another request. Reload it and try again.',
                errorCode: 'INVOICE_CHANGED_CONCURRENTLY',
            );
        }
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        try {
            return Invoice::restore(
                id: new InvoiceId(value: $model->id),
                customer: new Customer(name: $model->customer_name, email: $model->customer_email),
                productLines: $model->productLines
                    ->map(static fn (ProductLineModel $line): ProductLine => new ProductLine(
                        productName: $line->name,
                        quantity: $line->quantity,
                        unitPrice: $line->unit_price,
                    ))
                    ->values()
                    ->all(),
                status: InvoiceStatus::tryFrom($model->status)
                    ?? throw new InvalidArgumentException("Unknown invoice status '{$model->status}'."),
            );
        } catch (BusinessRuleViolation|InvalidArgumentException $exception) {
            // bad data in the database is a 500, not a 422 for the client
            throw new UnexpectedValueException("Stored invoice {$model->id} is inconsistent.", previous: $exception);
        }
    }
}
