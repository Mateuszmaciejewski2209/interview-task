<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Persistence\DatabaseInvoiceRepository;
use Modules\Shared\Domain\Exceptions\Conflict;
use Tests\Support\Invoices\InvoiceMother;

// Each new DatabaseInvoiceRepository stands for a separate request.

function storedInvoice(): Invoice
{
    $invoice = InvoiceMother::draft();
    (new DatabaseInvoiceRepository)->save($invoice);

    return $invoice;
}

it('restores a saved invoice with its customer, ordered lines and status', function (): void {
    $invoice = InvoiceMother::draft(lines: [
        new ProductLine('Zeta', 1, 100),
        new ProductLine('Alpha', 2, 200),
        new ProductLine('Mid', 1, 300),
    ]);
    (new DatabaseInvoiceRepository)->save($invoice);

    $restored = (new DatabaseInvoiceRepository)->load($invoice->id);

    expect($restored)->not->toBe($invoice)
        ->and($restored->id->equals($invoice->id))->toBeTrue()
        ->and($restored->customer)->toEqual($invoice->customer)
        ->and($restored->status)->toBe(InvoiceStatus::Draft)
        ->and(array_map(static fn (ProductLine $line): string => $line->productName, $restored->productLines))
        ->toBe(['Zeta', 'Alpha', 'Mid'])
        ->and($restored->totalPrice)->toBe(800);
});

it('stores the new status of a loaded invoice', function (): void {
    $invoice = storedInvoice();
    $invoices = new DatabaseInvoiceRepository;
    $loaded = $invoices->load($invoice->id);

    $loaded->send();
    $invoices->save($loaded);

    expect((new DatabaseInvoiceRepository)->load($invoice->id)->status)->toBe(InvoiceStatus::Sending);
});

it('refuses a repeated create instead of resetting the stored invoice', function (): void {
    $invoice = storedInvoice();
    $invoices = new DatabaseInvoiceRepository;
    $loaded = $invoices->load($invoice->id);
    $loaded->send();
    $invoices->save($loaded);

    $repeat = Invoice::draft(
        id: $invoice->id,
        customer: new Customer(name: 'Someone Else', email: 'else@example.com'),
        productLines: [],
    );

    expect(fn () => (new DatabaseInvoiceRepository)->save($repeat))
        ->toThrow(fn (Conflict $conflict) => expect($conflict->errorCode)->toBe('INVOICE_ALREADY_EXISTS'));

    $kept = (new DatabaseInvoiceRepository)->load($invoice->id);
    expect($kept->status)->toBe(InvoiceStatus::Sending)
        ->and($kept->customer->name)->toBe('Jane Doe');
});

it('refuses a change based on a stale state instead of overwriting', function (): void {
    $invoice = storedInvoice();
    // two requests load the same draft, the first one sends it and confirms the delivery
    [$first, $second] = [new DatabaseInvoiceRepository, new DatabaseInvoiceRepository];
    $firstCopy = $first->load($invoice->id);
    $secondCopy = $second->load($invoice->id);
    $firstCopy->send();
    $first->save($firstCopy);
    $firstCopy->confirmDelivery();
    $first->save($firstCopy);

    $secondCopy->send();

    expect(fn () => $second->save($secondCopy))
        ->toThrow(fn (Conflict $conflict) => expect($conflict->errorCode)->toBe('INVOICE_CHANGED_CONCURRENTLY'))
        ->and((new DatabaseInvoiceRepository)->load($invoice->id)->status)->toBe(InvoiceStatus::SentToClient);
});

it('reports an unknown invoice as not found', function (): void {
    (new DatabaseInvoiceRepository)->load(InvoiceId::generate());
})->throws(InvoiceNotFound::class);

it('treats a stored record the model rejects as a server fault', function (array $invoiceRow, array $lineRow): void {
    $id = InvoiceId::generate();
    DB::table('invoices')->insert(array_replace([
        'id' => $id->value,
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'status' => 'draft',
    ], $invoiceRow));
    DB::table('invoice_product_lines')->insert(array_replace([
        'id' => InvoiceId::generate()->value,
        'invoice_id' => $id->value,
        'position' => 0,
        'name' => 'Consulting',
        'quantity' => 1,
        'unit_price' => 100,
    ], $lineRow));

    (new DatabaseInvoiceRepository)->load($id);
})->with([
    'unknown status' => [['status' => 'paid'], []],
    'invalid customer e-mail' => [['customer_email' => 'nobody'], []],
    'zero unit price' => [[], ['unit_price' => 0]],
    'negative quantity' => [[], ['quantity' => -1]],
])->throws(UnexpectedValueException::class);
