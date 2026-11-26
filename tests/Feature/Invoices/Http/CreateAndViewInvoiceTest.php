<?php

declare(strict_types=1);

use Modules\Invoices\Domain\ValueObjects\InvoiceId;

describe('create', function (): void {
    it('returns the new identity and where to read it', function (): void {
        $response = $this->postJson(route('invoices.store'), $this->invoicePayload())->assertCreated();

        $id = $response->json('data.id');
        expect($response->json())->toBe(['data' => ['id' => $id]])
            ->and($id)->toBeUuid();
        $response->assertHeader('Location', route('invoices.show', ['invoiceId' => $id]));
    });

    it('accepts an invoice without product lines', function (array $payload): void {
        $id = $this->createInvoice($payload);

        $this->getJson(route('invoices.show', ['invoiceId' => $id]))
            ->assertOk()
            ->assertJsonPath('data.productLines', [])
            ->assertJsonPath('data.totalPrice', 0);
    })->with([
        'empty list' => [['productLines' => []]],
        'omitted' => [['productLines' => null]],
    ]);

    it('treats explicit null product lines as none', function (): void {
        $response = $this->postJson(route('invoices.store'), [...$this->invoicePayload(), 'productLines' => null])
            ->assertCreated();

        $this->getJson($response->headers->get('Location'))->assertJsonPath('data.productLines', []);
    });

    it('always creates a draft, whatever the client asks for', function (): void {
        $id = $this->createInvoice(['status' => 'sent-to-client']);

        expect($this->statusOf($id))->toBe('draft');
    });

    it('rejects a malformed request and stores nothing', function (array $overrides, string $field): void {
        $this->postJson(route('invoices.store'), $this->invoicePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('invoices', 0);
    })->with(function (): iterable {
        $line = ['productName' => 'Consulting', 'quantity' => 3, 'unitPrice' => 1500];

        yield 'missing customer name' => [['customerName' => null], 'customerName'];
        yield 'invalid customer e-mail' => [['customerEmail' => 'jane.example.com'], 'customerEmail'];
        yield 'e-mail the domain refuses (non-ASCII local part)' => [['customerEmail' => 'zażółć@example.com'], 'customerEmail'];
        yield 'product lines not a list' => [['productLines' => ['first' => $line]], 'productLines'];
        yield 'missing product name' => [['productLines' => [[...$line, 'productName' => '']]], 'productLines.0.productName'];
        yield 'zero quantity' => [['productLines' => [[...$line, 'quantity' => 0]]], 'productLines.0.quantity'];
        yield 'zero unit price' => [['productLines' => [[...$line, 'unitPrice' => 0]]], 'productLines.0.unitPrice'];
        yield 'negative quantity' => [['productLines' => [[...$line, 'quantity' => -1]]], 'productLines.0.quantity'];
        yield 'fractional unit price' => [['productLines' => [[...$line, 'unitPrice' => 15.5]]], 'productLines.0.unitPrice'];
        yield 'numeric string quantity' => [['productLines' => [[...$line, 'quantity' => '3']]], 'productLines.0.quantity'];
        yield 'unit price beyond storage' => [['productLines' => [[...$line, 'unitPrice' => 2_147_483_648]]], 'productLines.0.unitPrice'];
    });
});

describe('error format', function (): void {
    it('answers in JSON without an Accept header', function (): void {
        // plain form post without an Accept header
        $this->post(route('invoices.store'), ['customerEmail' => 'nope'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors(['customerName', 'customerEmail']);

        $this->get('/api/unknown-route')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    });
});

describe('view', function (): void {
    it('returns the invoice with derived totals', function (): void {
        $id = $this->createInvoice();

        $this->getJson(route('invoices.show', ['invoiceId' => $id]))
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $id,
                'status' => 'draft',
                'customerName' => 'Jane Doe',
                'customerEmail' => 'jane@example.com',
                'productLines' => [
                    ['productName' => 'Consulting', 'quantity' => 3, 'unitPrice' => 1500, 'totalUnitPrice' => 4500],
                    ['productName' => 'Licence', 'quantity' => 1, 'unitPrice' => 9900, 'totalUnitPrice' => 9900],
                ],
                'totalPrice' => 14400,
            ]]);
    });

    it('reports an unknown invoice as not found', function (): void {
        $this->getJson(route('invoices.show', ['invoiceId' => InvoiceId::generate()->value]))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'INVOICE_NOT_FOUND');
    });

    it('does not route a malformed identity', function (): void {
        $this->getJson('/api/invoices/not-a-uuid')->assertNotFound();
    });
});
