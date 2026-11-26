<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Shared\Domain\BusinessRuleViolation;

/** Quantity and unit price must be positive, so every line can be sent. */
final class ProductLine
{
    public readonly string $productName;

    public readonly int $quantity;

    public readonly int $unitPrice;

    public int $totalUnitPrice {
        get => $this->quantity * $this->unitPrice;
    }

    public function __construct(string $productName, int $quantity, int $unitPrice)
    {
        $productName = trim($productName);
        if ($productName === '') {
            throw BusinessRuleViolation::because('Product name is required.');
        }
        if ($quantity < 1) {
            throw BusinessRuleViolation::because('Quantity must be a positive integer.');
        }
        if ($unitPrice < 1) {
            throw BusinessRuleViolation::because('Unit price must be a positive integer.');
        }
        // on int overflow PHP returns a float
        if (! is_int($quantity * $unitPrice)) {
            throw BusinessRuleViolation::because('Total unit price is too large.');
        }
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }
}
