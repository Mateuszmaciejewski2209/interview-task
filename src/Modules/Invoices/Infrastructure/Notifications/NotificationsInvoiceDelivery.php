<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Exception;
use Modules\Invoices\Application\Ports\InvoiceDelivery;
use Modules\Invoices\Domain\Invoice;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Modules\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Ramsey\Uuid\Uuid;

/** The invoice id is the notification reference, the webhook sends it back. */
final readonly class NotificationsInvoiceDelivery implements InvoiceDelivery
{
    public function __construct(private NotificationFacadeInterface $notifications) {}

    public function send(Invoice $invoice): void
    {
        try {
            $this->notifications->notify(new NotifyData(
                resourceId: Uuid::fromString($invoice->id->value),
                toEmail: $invoice->customer->email,
                subject: "Invoice {$invoice->id}",
                message: sprintf(
                    "Dear %s,\n\nplease find your invoice %s. Total price: %d.\n",
                    $invoice->customer->name,
                    $invoice->id,
                    $invoice->totalPrice,
                ),
            ));
        } catch (Exception $exception) {
            // only Exception, an Error is a bug and should stay a 500
            throw new ExternalServiceUnavailable(
                message: 'The notification service did not accept the invoice. Try sending it again later.',
                errorCode: 'INVOICE_DELIVERY_UNAVAILABLE',
                previous: $exception,
            );
        }
    }
}
