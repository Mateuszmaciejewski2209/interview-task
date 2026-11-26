<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;

/*
 * Two overlapping requests in separate PHP processes on a shared SQLite file
 * (the in-memory database has only one connection). The first send sleeps in
 * notify() and the second request comes in meanwhile.
 */

/** @param array<string, mixed> $input */
function startRequest(array $input): InvokedProcess
{
    return Process::env(test()->env)
        ->path(base_path())
        ->start([PHP_BINARY, 'tests/Support/Concurrency/request.php', json_encode($input + ['marker' => test()->marker])]);
}

/** @return array{status: int, body: mixed} */
function finishRequest(InvokedProcess $process): array
{
    $result = $process->wait();

    return json_decode($result->output(), true) ?? throw new RuntimeException('Request process failed: '.$result->errorOutput());
}

/**
 * @param  array<string, mixed>  $input
 * @return array{status: int, body: mixed}
 */
function runRequest(array $input): array
{
    return finishRequest(startRequest($input));
}

/** Waits until the first request is inside notify(). */
function waitUntilNotifying(InvokedProcess $first): void
{
    $deadline = microtime(true) + 10;
    while (! file_exists(test()->marker)) {
        if (microtime(true) > $deadline || ! $first->running()) {
            throw new RuntimeException('The first request never started e-mailing: '.$first->wait()->output());
        }
        usleep(20_000);
    }
}

function sentEmails(): int
{
    return file_exists(test()->marker.'.sent') ? count(file(test()->marker.'.sent')) : 0;
}

beforeEach(function (): void {
    $this->database = tempnam(sys_get_temp_dir(), 'invoices-race-');
    $this->marker = $this->database.'.notifying';
    $this->env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $this->database,
        'NOTIFICATION_WEBHOOK_SECRET' => config('services.notifications.webhook_secret'),
    ];
    Process::env($this->env)->path(base_path())->run([PHP_BINARY, 'artisan', 'migrate', '--force'])->throw();

    $this->invoiceId = runRequest(['method' => 'POST', 'uri' => '/api/invoices', 'body' => [
        'customerName' => 'Jane Doe',
        'customerEmail' => 'jane@example.com',
        'productLines' => [['productName' => 'Consulting', 'quantity' => 3, 'unitPrice' => 1500]],
    ]])['body']['data']['id'];
});

afterEach(function (): void {
    foreach ([$this->database, $this->marker, $this->marker.'.sent'] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

it('lets a second send wait for the first one and then refuses it, with one e-mail', function (): void {
    $first = startRequest(['method' => 'POST', 'uri' => "/api/invoices/{$this->invoiceId}/send", 'hold' => 2]);
    waitUntilNotifying($first);

    $second = runRequest(['method' => 'POST', 'uri' => "/api/invoices/{$this->invoiceId}/send"]);

    expect(finishRequest($first)['status'])->toBe(204)
        ->and($second['status'])->toBe(422)
        ->and($second['body']['error']['code'])->toBe('INVOICE_MUST_BE_DRAFT')
        ->and(sentEmails())->toBe(1);
});

it('lets a delivery report that overtakes the commit of its send wait for it', function (): void {
    $first = startRequest(['method' => 'POST', 'uri' => "/api/invoices/{$this->invoiceId}/send", 'hold' => 2]);
    waitUntilNotifying($first);

    $report = runRequest([
        'method' => 'GET',
        'uri' => "/api/notification/hook/delivered/{$this->invoiceId}",
        'headers' => ['X-Notification-Signature' => app(WebhookSignature::class)
            ->header(action: 'delivered', reference: $this->invoiceId, timestamp: now()->getTimestamp())],
    ]);

    expect(finishRequest($first)['status'])->toBe(204)
        ->and($report['status'])->toBe(204)
        ->and(runRequest(['method' => 'GET', 'uri' => "/api/invoices/{$this->invoiceId}"])['body']['data']['status'])
        ->toBe('sent-to-client');
});
