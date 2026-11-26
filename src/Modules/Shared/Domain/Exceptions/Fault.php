<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

/** Expected error with a stable code for API clients. The subclass decides the HTTP status. */
abstract class Fault extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
