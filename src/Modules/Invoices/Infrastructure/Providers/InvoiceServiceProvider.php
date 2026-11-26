<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Bus\Dispatcher as CommandDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Commands\ConfirmInvoiceDeliveryCommand;
use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\Commands\Handlers\ConfirmInvoiceDeliveryHandler;
use Modules\Invoices\Application\Commands\Handlers\CreateInvoiceHandler;
use Modules\Invoices\Application\Commands\Handlers\SendInvoiceHandler;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Ports\InvoiceDelivery;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Infrastructure\Notifications\ConfirmInvoiceDeliveryOnWebhook;
use Modules\Invoices\Infrastructure\Notifications\NotificationsInvoiceDelivery;
use Modules\Invoices\Infrastructure\Persistence\DatabaseInvoiceRepository;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: the repository remembers loaded invoices, load and save need the same instance
        $this->app->scoped(InvoiceRepository::class, DatabaseInvoiceRepository::class);
        $this->app->scoped(InvoiceDelivery::class, NotificationsInvoiceDelivery::class);
    }

    public function boot(CommandDispatcher $commands, EventDispatcher $events): void
    {
        $commands->map([
            CreateInvoiceCommand::class => CreateInvoiceHandler::class,
            SendInvoiceCommand::class => SendInvoiceHandler::class,
            ConfirmInvoiceDeliveryCommand::class => ConfirmInvoiceDeliveryHandler::class,
        ]);

        $events->listen(WebhookDeliveredEvent::class, ConfirmInvoiceDeliveryOnWebhook::class);
    }
}
