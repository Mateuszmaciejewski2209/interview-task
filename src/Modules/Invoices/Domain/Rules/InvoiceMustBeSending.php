<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Rules;

use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Shared\Domain\BusinessRule;

final readonly class InvoiceMustBeSending implements BusinessRule
{
    public function __construct(private InvoiceStatus $status) {}

    public function isBroken(): bool
    {
        return $this->status !== InvoiceStatus::Sending;
    }

    public function message(): string
    {
        return 'Only an invoice that is being sent can be confirmed as delivered.';
    }
}
