<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use InvalidArgumentException;
use Modules\Invoices\Domain\Rules\InvoiceMustBeDraft;
use Modules\Invoices\Domain\Rules\InvoiceMustBeSending;
use Modules\Invoices\Domain\Rules\InvoiceMustHaveProductLines;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\InvoiceStatus;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Shared\Domain\BusinessRuleViolation;
use Modules\Shared\Domain\ChecksBusinessRules;

/** Customer and lines are set on creation, after that only the status changes. */
final class Invoice
{
    use ChecksBusinessRules;

    public int $totalPrice {
        get => array_sum(array_map(
            static fn (ProductLine $line): int => $line->totalUnitPrice,
            $this->productLines,
        ));
    }

    /** @param list<ProductLine> $productLines */
    private function __construct(
        public readonly InvoiceId $id,
        public readonly Customer $customer,
        public readonly array $productLines,
        public private(set) InvoiceStatus $status,
    ) {
        if (! array_is_list($productLines)) {
            throw new InvalidArgumentException('Product lines must be a list.');
        }
        $total = 0;
        foreach ($productLines as $line) {
            if (! $line instanceof ProductLine) {
                throw new InvalidArgumentException('Every product line must be a '.ProductLine::class.'.');
            }
            $total += $line->totalUnitPrice;
        }
        if (! is_int($total)) {
            throw BusinessRuleViolation::because('Invoice total price is too large.');
        }
    }

    /** @param list<ProductLine> $productLines */
    public static function draft(InvoiceId $id, Customer $customer, array $productLines): self
    {
        return new self(id: $id, customer: $customer, productLines: $productLines, status: InvoiceStatus::Draft);
    }

    /**
     * For the repository. Sending rules are not checked here, they apply in send().
     *
     * @param  list<ProductLine>  $productLines
     */
    public static function restore(
        InvoiceId $id,
        Customer $customer,
        array $productLines,
        InvoiceStatus $status,
    ): self {
        return new self(id: $id, customer: $customer, productLines: $productLines, status: $status);
    }

    public function send(): void
    {
        $this->checkRule(new InvoiceMustBeDraft(status: $this->status));
        // quantity and unit price are already checked in ProductLine
        $this->checkRule(new InvoiceMustHaveProductLines(productLines: $this->productLines));
        $this->status = InvoiceStatus::Sending;
    }

    /** The provider can report the same delivery more than once, a repeat is a no-op. */
    public function confirmDelivery(): void
    {
        if ($this->status === InvoiceStatus::SentToClient) {
            return;
        }
        $this->checkRule(new InvoiceMustBeSending(status: $this->status));
        $this->status = InvoiceStatus::SentToClient;
    }
}
