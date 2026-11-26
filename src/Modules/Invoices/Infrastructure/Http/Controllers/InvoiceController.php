<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Application\Queries\GetInvoiceQuery;
use Modules\Invoices\Application\Queries\Handlers\GetInvoiceQueryHandler;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Infrastructure\Http\Requests\CreateInvoiceRequest;
use Modules\Invoices\Infrastructure\Http\Resources\InvoiceResource;
use Modules\Shared\Application\CommandBus;

final readonly class InvoiceController
{
    public function __construct(private CommandBus $commands) {}

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        /** @var array{customerName: string, customerEmail: string, productLines?: list<array{productName: string, quantity: int, unitPrice: int}>} $data */
        $data = $request->validated();
        $invoiceId = InvoiceId::generate();

        $this->commands->dispatch(new CreateInvoiceCommand(
            invoiceId: $invoiceId,
            customerName: $data['customerName'],
            customerEmail: $data['customerEmail'],
            productLines: array_map(static fn (array $line): ProductLineData => new ProductLineData(
                productName: $line['productName'],
                quantity: $line['quantity'],
                unitPrice: $line['unitPrice'],
            ), $data['productLines'] ?? []),
        ));

        return new JsonResponse(
            data: ['data' => ['id' => $invoiceId->value]],
            status: 201,
            headers: ['Location' => route('invoices.show', ['invoiceId' => $invoiceId->value])],
        );
    }

    public function show(string $invoiceId, GetInvoiceQueryHandler $invoices): InvoiceResource
    {
        return new InvoiceResource($invoices(new GetInvoiceQuery(invoiceId: new InvoiceId(value: $invoiceId))));
    }

    public function send(string $invoiceId): Response
    {
        $this->commands->dispatch(new SendInvoiceCommand(invoiceId: new InvoiceId(value: $invoiceId)));

        return response()->noContent();
    }
}
