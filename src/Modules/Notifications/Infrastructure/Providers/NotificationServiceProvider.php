<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Modules\Notifications\Application\Facades\NotificationFacade;
use Modules\Notifications\Infrastructure\Drivers\DriverInterface;
use Modules\Notifications\Infrastructure\Drivers\DummyDriver;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;

final class NotificationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->scoped(NotificationFacadeInterface::class, NotificationFacade::class);
        $this->app->scoped(DriverInterface::class, DummyDriver::class);
        $this->app->singleton(WebhookSignature::class, static fn (Application $app): WebhookSignature => new WebhookSignature(
            secret: (string) $app->make('config')->get('services.notifications.webhook_secret'),
            toleranceSeconds: (int) $app->make('config')->get('services.notifications.webhook_tolerance_seconds'),
        ));
    }

    /** @return array<class-string> */
    public function provides(): array
    {
        return [
            NotificationFacadeInterface::class,
            DriverInterface::class,
            WebhookSignature::class,
        ];
    }
}
