<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Rules;

use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Shared\Domain\BusinessRule;

final readonly class InvoiceMustHaveProductLines implements BusinessRule
{
    /** @param list<ProductLine> $productLines */
    public function __construct(private array $productLines) {}

    public function isBroken(): bool
    {
        return $this->productLines === [];
    }

    public function message(): string
    {
        return 'An invoice needs at least one product line to be sent.';
    }
}
