<?php

declare(strict_types=1);

use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Shared\Domain\BusinessRuleViolation;

describe('product line', function (): void {
    it('derives the total unit price from quantity and unit price', function (): void {
        $line = new ProductLine(productName: ' Consulting ', quantity: 3, unitPrice: 1500);

        expect($line->productName)->toBe('Consulting')
            ->and($line->totalUnitPrice)->toBe(4500);
    });

    it('accepts the smallest positive values', function (): void {
        expect((new ProductLine(productName: 'Sample', quantity: 1, unitPrice: 1))->totalUnitPrice)->toBe(1);
    });

    it('rejects a blank name and amounts that are not positive', function (string $productName, int $quantity, int $unitPrice): void {
        new ProductLine(productName: $productName, quantity: $quantity, unitPrice: $unitPrice);
    })->with([
        'blank name' => [' ', 1, 1],
        'zero quantity' => ['Consulting', 0, 1500],
        'zero unit price' => ['Consulting', 3, 0],
        'negative quantity' => ['Consulting', -1, 1500],
        'negative unit price' => ['Consulting', 3, -1],
        'total beyond the integer range' => ['Consulting', 2, intdiv(PHP_INT_MAX, 2) + 1],
    ])->throws(BusinessRuleViolation::class);
});

describe('customer', function (): void {
    it('is trimmed', function (): void {
        $customer = new Customer(name: ' Jane Doe ', email: ' jane@example.com ');

        expect($customer->name)->toBe('Jane Doe')
            ->and($customer->email)->toBe('jane@example.com');
    });

    it('rejects an unusable name or e-mail', function (string $name, string $email): void {
        new Customer(name: $name, email: $email);
    })->with([
        'blank name' => ['  ', 'jane@example.com'],
        'not an e-mail' => ['Jane Doe', 'jane.example.com'],
        'blank e-mail' => ['Jane Doe', ''],
    ])->throws(BusinessRuleViolation::class);
});

describe('invoice identity', function (): void {
    it('is generated as a distinct UUIDv7', function (): void {
        $id = InvoiceId::generate();

        expect($id->value)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
            ->and($id->equals(InvoiceId::generate()))->toBeFalse();
    });

    it('is canonical lowercase', function (): void {
        $id = new InvoiceId(value: '0192F3A4-5B6C-7D8E-9F01-23456789ABCD');

        expect($id->value)->toBe('0192f3a4-5b6c-7d8e-9f01-23456789abcd')
            ->and($id->equals(new InvoiceId(value: '0192f3a4-5b6c-7d8e-9f01-23456789abcd')))->toBeTrue();
    });

    it('rejects a malformed value', function (): void {
        new InvoiceId(value: 'not-a-uuid');
    })->throws(BusinessRuleViolation::class);
});
