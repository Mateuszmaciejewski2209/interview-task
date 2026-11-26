<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Infrastructure\Http\Controllers\InvoiceController;

Route::prefix('invoices')->name('invoices.')->group(static function (): void {
    Route::post('/', [InvoiceController::class, 'store'])->name('store');
    Route::get('/{invoiceId}', [InvoiceController::class, 'show'])->whereUuid('invoiceId')->name('show');
    Route::post('/{invoiceId}/send', [InvoiceController::class, 'send'])->whereUuid('invoiceId')->name('send');
});
