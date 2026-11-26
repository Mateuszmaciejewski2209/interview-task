<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Application\Commands\ConfirmInvoiceDeliveryCommand;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Modules\Shared\Application\CommandBus;
use Psr\Log\LoggerInterface;

/**
 * Runs synchronously in the webhook request, so an error goes back to the provider
 * as non-2xx and it retries. Unknown references are ignored, Notifications is not
 * used only for invoices.
 */
final readonly class ConfirmInvoiceDeliveryOnWebhook
{
    public function __construct(
        private CommandBus $commands,
        private LoggerInterface $logger,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $invoiceId = new InvoiceId(value: $event->resourceId->toString());
        try {
            $this->commands->dispatch(new ConfirmInvoiceDeliveryCommand(invoiceId: $invoiceId));
        } catch (InvoiceNotFound) {
            $this->logger->info('Delivery report ignored: the reference is not an invoice.', [
                'reference' => $invoiceId->value,
            ]);
        }
    }
}
