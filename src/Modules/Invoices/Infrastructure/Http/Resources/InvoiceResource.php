<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Data\ProductLineView;

/** @property InvoiceView $resource */
final class InvoiceResource extends JsonResource
{
    public function __construct(InvoiceView $invoice)
    {
        parent::__construct($invoice);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'customerName' => $invoice->customerName,
            'customerEmail' => $invoice->customerEmail,
            'productLines' => array_map(static fn (ProductLineView $line): array => [
                'productName' => $line->productName,
                'quantity' => $line->quantity,
                'unitPrice' => $line->unitPrice,
                'totalUnitPrice' => $line->totalUnitPrice,
            ], $invoice->productLines),
            'totalPrice' => $invoice->totalPrice,
        ];
    }
}
