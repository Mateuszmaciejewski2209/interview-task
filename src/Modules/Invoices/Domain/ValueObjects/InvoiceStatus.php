<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

/** draft -> sending -> sent-to-client, transitions are in Invoice. */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case SentToClient = 'sent-to-client';
}
