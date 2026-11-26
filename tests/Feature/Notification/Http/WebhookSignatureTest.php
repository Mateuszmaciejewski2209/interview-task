<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;
use Modules\Notifications\Presentation\Http\Middleware\VerifyWebhookSignature;

const SIGNED_REFERENCE = '0192f3a4-5b6c-7d8e-9f01-23456789abcd';

function callDeliveredHook(?string $signature): TestResponse
{
    return test()->getJson(
        route('notification.hook', ['action' => 'delivered', 'reference' => SIGNED_REFERENCE]),
        $signature === null ? [] : [VerifyWebhookSignature::HEADER => $signature],
    );
}

function providerSignature(string $action = 'delivered', string $reference = SIGNED_REFERENCE, int $shift = 0): string
{
    return app(WebhookSignature::class)->header(
        action: $action,
        reference: $reference,
        timestamp: now()->getTimestamp() + $shift,
    );
}

beforeEach(function (): void {
    Event::fake([WebhookDeliveredEvent::class]);
    $this->freezeTime();
});

it('accepts a report signed with the shared secret within the time window', function (int $shift): void {
    callDeliveredHook(providerSignature(shift: $shift))->assertNoContent();

    Event::assertDispatched(WebhookDeliveredEvent::class);
})->with([
    'now' => [0],
    'oldest accepted' => [-300],
    'clock skew ahead' => [300],
]);

it('rejects a report that is not properly signed', function (?string $signature): void {
    callDeliveredHook($signature)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');

    Event::assertNotDispatched(WebhookDeliveredEvent::class);
})->with([
    'missing' => fn () => null,
    'malformed' => fn () => 'sha256=whatever',
    'another secret' => fn () => (new WebhookSignature('another-secret'))->header('delivered', SIGNED_REFERENCE, now()->getTimestamp()),
    'moved to another reference' => fn () => providerSignature(reference: '0192f3a4-5b6c-7d8e-9f01-000000000000'),
    'moved to another action' => fn () => providerSignature(action: 'bounced'),
    'tampered digest' => fn () => substr(providerSignature(), 0, -1).(str_ends_with(providerSignature(), '0') ? '1' : '0'),
    'expired (replay)' => fn () => providerSignature(shift: -301),
    'too far in the future' => fn () => providerSignature(shift: 301),
]);

it('points to the provider simulator only on a local setup', function (string $environment, bool $hinted): void {
    $this->app['env'] = $environment;

    $response = callDeliveredHook(null)->assertUnauthorized();

    expect($response->json('error.hint') !== null)->toBe($hinted);
})->with([
    'local' => ['local', true],
    'production' => ['production', false],
]);

it('lets the provider simulator refuse a reference that is not a UUID', function (): void {
    $this->artisan('notifications:simulate-delivered', ['reference' => 'not-a-uuid'])->assertExitCode(2);

    Event::assertNotDispatched(WebhookDeliveredEvent::class);
});

it('fails closed when the secret is not configured', function (): void {
    config()->set('services.notifications.webhook_secret', '');

    // valid format and timestamp, so it gets as far as the secret check
    callDeliveredHook('t='.now()->getTimestamp().',v1='.str_repeat('0', 64))->assertServerError();

    Event::assertNotDispatched(WebhookDeliveredEvent::class);
});
