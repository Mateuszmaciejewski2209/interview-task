<?php

declare(strict_types=1);

namespace Modules\Shared\Domain;

use DomainException;

/** fromRule() for named rules, because() for simple value checks that don't need a rule class. */
final class BusinessRuleViolation extends DomainException
{
    /** @param class-string<BusinessRule>|null $rule */
    private function __construct(
        string $message,
        public readonly ?string $rule = null,
    ) {
        parent::__construct($message);
    }

    public static function fromRule(BusinessRule $rule): self
    {
        return new self(message: $rule->message(), rule: $rule::class);
    }

    public static function because(string $message): self
    {
        return new self(message: $message);
    }
}
