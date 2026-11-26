<?php

declare(strict_types=1);

namespace Modules\Shared\Domain;

trait ChecksBusinessRules
{
    protected function checkRule(BusinessRule $rule): void
    {
        if ($rule->isBroken()) {
            throw BusinessRuleViolation::fromRule($rule);
        }
    }
}
