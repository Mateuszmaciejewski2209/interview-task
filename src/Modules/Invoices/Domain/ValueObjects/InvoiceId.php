<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Shared\Domain\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;
use Stringable;

/** UUIDv7, generated in the controller before the create command is dispatched. */
final readonly class InvoiceId implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        if (! Uuid::isValid($value)) {
            throw BusinessRuleViolation::because('Invalid invoice identifier.');
        }
        $this->value = strtolower($value);
    }

    public static function generate(): self
    {
        return new self(value: Uuid::uuid7()->toString());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
