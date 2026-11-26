<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

/** Handlers return nothing, the result is read with a separate query. */
interface CommandBus
{
    public function dispatch(object $command): void;
}
