<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Rules;

use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Shared\Domain\BusinessRule;

final readonly class InvoiceMustBeDraft implements BusinessRule
{
    public function __construct(private InvoiceStatus $status) {}

    public function isBroken(): bool
    {
        return $this->status !== InvoiceStatus::Draft;
    }

    public function message(): string
    {
        return 'Only a draft invoice can be sent.';
    }
}
