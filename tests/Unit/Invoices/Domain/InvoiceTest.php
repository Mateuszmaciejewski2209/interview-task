<?php

declare(strict_types=1);

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Rules\InvoiceMustBeDraft;
use Modules\Invoices\Domain\Rules\InvoiceMustBeSending;
use Modules\Invoices\Domain\Rules\InvoiceMustHaveProductLines;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Shared\Domain\BusinessRuleViolation;
use Tests\Support\Invoices\InvoiceMother;

describe('creating', function (): void {
    it('starts as a draft with the supplied identity, customer and lines', function (): void {
        $id = InvoiceId::generate();
        $lines = [new ProductLine('Consulting', 3, 1500), new ProductLine('Licence', 1, 9900)];

        $invoice = Invoice::draft(id: $id, customer: InvoiceMother::customer(), productLines: $lines);

        expect($invoice->id)->toBe($id)
            ->and($invoice->status)->toBe(InvoiceStatus::Draft)
            ->and($invoice->customer->name)->toBe('Jane Doe')
            ->and($invoice->productLines)->toBe($lines);
    });

    it('may have no product lines', function (): void {
        $invoice = InvoiceMother::draft(lines: []);

        expect($invoice->productLines)->toBe([])
            ->and($invoice->totalPrice)->toBe(0);
    });

    it('derives the total price from the total unit prices', function (): void {
        $invoice = InvoiceMother::draft(lines: [new ProductLine('Consulting', 3, 1500), new ProductLine('Licence', 1, 9900)]);

        expect(array_map(static fn (ProductLine $line): int => $line->totalUnitPrice, $invoice->productLines))
            ->toBe([4500, 9900])
            ->and($invoice->totalPrice)->toBe(14400);
    });

    it('rejects a total price beyond the integer range', function (): void {
        InvoiceMother::draft(lines: [new ProductLine('A', 1, PHP_INT_MAX), new ProductLine('B', 1, 1)]);
    })->throws(BusinessRuleViolation::class);

    it('changes its status only through behaviour', function (): void {
        $invoice = InvoiceMother::draft();

        $invoice->status = InvoiceStatus::SentToClient;
    })->throws(Error::class);
});

describe('sending', function (): void {
    it('moves a draft with lines to sending', function (): void {
        $invoice = InvoiceMother::draft();

        $invoice->send();

        expect($invoice->status)->toBe(InvoiceStatus::Sending);
    });

    it('is allowed only from a draft', function (InvoiceStatus $status): void {
        $invoice = InvoiceMother::restored(status: $status);

        expect($invoice->send(...))->toBreak(InvoiceMustBeDraft::class)
            ->and($invoice->status)->toBe($status);
    })->with([
        'sending' => [InvoiceStatus::Sending],
        'sent to client' => [InvoiceStatus::SentToClient],
    ]);

    it('needs at least one product line', function (): void {
        $invoice = InvoiceMother::draft(lines: []);

        expect($invoice->send(...))->toBreak(InvoiceMustHaveProductLines::class)
            ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    });

    it('checks the status before the lines', function (): void {
        $invoice = InvoiceMother::restored(status: InvoiceStatus::Sending, lines: []);

        expect($invoice->send(...))->toBreak(InvoiceMustBeDraft::class);
    });
});

describe('confirming delivery', function (): void {
    it('marks a sending invoice as sent to the client', function (): void {
        $invoice = InvoiceMother::draft();
        $invoice->send();

        $invoice->confirmDelivery();

        expect($invoice->status)->toBe(InvoiceStatus::SentToClient);
    });

    it('accepts a repeated confirmation without a change', function (): void {
        $invoice = InvoiceMother::restored(status: InvoiceStatus::SentToClient);

        $invoice->confirmDelivery();

        expect($invoice->status)->toBe(InvoiceStatus::SentToClient);
    });

    it('rejects a confirmation for a draft', function (): void {
        $invoice = InvoiceMother::draft();

        expect($invoice->confirmDelivery(...))->toBreak(InvoiceMustBeSending::class)
            ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    });
});

describe('restoring', function (): void {
    it('keeps the stored status without applying the sending rules', function (): void {
        $invoice = InvoiceMother::restored(status: InvoiceStatus::SentToClient, lines: []);

        expect($invoice->status)->toBe(InvoiceStatus::SentToClient)
            ->and($invoice->totalPrice)->toBe(0);
    });

    it('rejects lines that are not product lines', function (): void {
        Invoice::restore(
            id: InvoiceId::generate(),
            customer: InvoiceMother::customer(),
            productLines: [['productName' => 'Consulting']],
            status: InvoiceStatus::Draft,
        );
    })->throws(InvalidArgumentException::class);
});
