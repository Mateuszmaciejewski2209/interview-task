<?php

declare(strict_types=1);

/*
 * One HTTP request in a separate process, used by ConcurrentRequestsTest.
 *
 * argv[1] (JSON): method, uri, marker, optional body, headers, hold
 * stdout (JSON): {"status": int, "body": mixed}
 *
 * The notification facade is faked, every e-mail is appended to "<marker>.sent".
 * With `hold` it touches <marker> and sleeps, so the transaction stays open.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';

/** @var array{method: string, uri: string, marker: string, body?: array<string, mixed>, headers?: array<string, string>, hold?: int} $input */
$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
// load deferred providers first, otherwise they would replace the fake later
$app->loadDeferredProviders();
$app->instance(NotificationFacadeInterface::class, new readonly class($input['marker'], $input['hold'] ?? 0) implements NotificationFacadeInterface
{
    public function __construct(private string $marker, private int $hold) {}

    public function notify(NotifyData $data): void
    {
        if ($this->hold > 0) {
            touch($this->marker);
            sleep($this->hold);
        }
        file_put_contents($this->marker.'.sent', $data->resourceId->toString().PHP_EOL, FILE_APPEND | LOCK_EX);
    }
});

$server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
foreach ($input['headers'] ?? [] as $name => $value) {
    $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
}
$request = Request::create(
    uri: $input['uri'],
    method: $input['method'],
    server: $server,
    content: isset($input['body']) ? json_encode($input['body'], JSON_THROW_ON_ERROR) : null,
);
$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
], JSON_THROW_ON_ERROR);
