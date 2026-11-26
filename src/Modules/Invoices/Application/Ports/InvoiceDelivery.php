<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Ports;

use Modules\Invoices\Domain\Invoice;
use Modules\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** The delivery itself is confirmed later by the webhook. */
interface InvoiceDelivery
{
    /** @throws ExternalServiceUnavailable */
    public function send(Invoice $invoice): void;
}
