<?php

use Modules\Invoices\Infrastructure\Providers\InvoiceServiceProvider;
use Modules\Notifications\Infrastructure\Providers\NotificationServiceProvider;
use Modules\Shared\Infrastructure\Providers\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    NotificationServiceProvider::class,
    InvoiceServiceProvider::class,
];
