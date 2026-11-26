<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Modules\Shared\Application\CommandBus;

/**
 * Runs each command in one DB transaction. Handlers are mapped with Dispatcher::map()
 * in the module providers. Only one attempt, a retry could send the e-mail twice.
 */
final readonly class TransactionalCommandBus implements CommandBus
{
    public function __construct(
        private Dispatcher $dispatcher,
        private ConnectionInterface $connection,
    ) {}

    public function dispatch(object $command): void
    {
        if (! $this->dispatcher->hasCommandHandler($command)) {
            throw new LogicException(sprintf('No handler is mapped for %s.', $command::class));
        }

        $this->connection->transaction(fn () => $this->dispatcher->dispatchNow($command));
    }
}
