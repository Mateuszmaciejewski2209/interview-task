<?php

declare(strict_types=1);

use Modules\Shared\Domain\BusinessRuleViolation;
use PHPUnit\Framework\Assert;
use Tests\Support\Invoices\InvoicesHttpTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature/Invoices/Persistence', 'Feature/Invoices/Concurrency');
pest()->extend(InvoicesHttpTestCase::class)->in('Feature/Invoices/Http');
pest()->extend(TestCase::class)->in('Feature/Notification');

// expect($invoice->send(...))->toBreak(InvoiceMustBeDraft::class)
expect()->extend('toBreak', function (string $rule) {
    try {
        ($this->value)();
    } catch (BusinessRuleViolation $violation) {
        Assert::assertSame($rule, $violation->rule, 'A different rule was broken.');

        return $this;
    }

    Assert::fail("Expected {$rule} to be broken.");
});
