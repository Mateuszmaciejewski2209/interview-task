<?php

declare(strict_types=1);

namespace Modules\Shared\Domain;

/** A broken rule ends up as a 422 with message() as the error text. */
interface BusinessRule
{
    public function isBroken(): bool;

    public function message(): string;
}
