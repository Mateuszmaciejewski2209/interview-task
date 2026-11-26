<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The domain checks the values again, this is here for proper 422 field errors. */
final class CreateInvoiceRequest extends FormRequest
{
    /** INTEGER is 32-bit on MySQL and PostgreSQL. */
    private const int MAX_STORED_INTEGER = 2_147_483_647;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customerName' => ['required', 'string', 'max:255'],
            // same check as in Customer (FILTER_VALIDATE_EMAIL)
            'customerEmail' => ['required', 'string', 'email:filter', 'max:255'],
            // omitted, null and [] all mean no lines
            'productLines' => ['sometimes', 'nullable', 'array', 'list'],
            'productLines.*' => ['array'],
            'productLines.*.productName' => ['required', 'string', 'max:255'],
            'productLines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_STORED_INTEGER],
            'productLines.*.unitPrice' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_STORED_INTEGER],
        ];
    }
}
