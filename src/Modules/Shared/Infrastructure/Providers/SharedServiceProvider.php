<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Infrastructure\Bus\TransactionalCommandBus;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CommandBus::class, TransactionalCommandBus::class);
    }
}
