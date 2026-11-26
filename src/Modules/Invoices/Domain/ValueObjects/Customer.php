<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Shared\Domain\BusinessRuleViolation;

final readonly class Customer
{
    public string $name;

    public string $email;

    public function __construct(string $name, string $email)
    {
        $name = trim($name);
        $email = trim($email);
        if ($name === '') {
            throw BusinessRuleViolation::because('Customer name is required.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw BusinessRuleViolation::because('Customer e-mail is not a valid address.');
        }
        $this->name = $name;
        $this->email = $email;
    }
}
