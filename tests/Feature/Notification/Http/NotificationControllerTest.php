<?php

declare(strict_types=1);

namespace Tests\Feature\Notification\Http;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Modules\Notifications\Infrastructure\Webhooks\WebhookSignature;
use Modules\Notifications\Presentation\Http\Middleware\VerifyWebhookSignature;
use Tests\TestCase;

final class NotificationControllerTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
    }

    public function testDeliveredHookDispatchesEvent(): void
    {
        Event::fake([WebhookDeliveredEvent::class]);

        $reference = $this->faker->uuid();
        $uri = route('notification.hook', [
            'action' => 'delivered',
            'reference' => $reference,
        ]);

        $this->getJson($uri, $this->signedAs('delivered', $reference))->assertNoContent();

        Event::assertDispatched(WebhookDeliveredEvent::class);
    }

    public function testUnknownActionReturnsNotFound(): void
    {
        $reference = $this->faker->uuid();
        $uri = route('notification.hook', [
            'action' => 'unknownaction',
            'reference' => $reference,
        ]);

        $this->getJson($uri, $this->signedAs('unknownaction', $reference))->assertNotFound();
    }

    public function testInvalidReferencePatternReturnsNotFound(): void
    {
        $uri = route('notification.hook', [
            'action' => 'delivered',
            'reference' => 'not-a-uuid',
        ]);

        $this->getJson($uri)->assertNotFound();
    }

    /** @return array<string, string> */
    private function signedAs(string $action, string $reference): array
    {
        return [VerifyWebhookSignature::HEADER => $this->app->make(WebhookSignature::class)
            ->header(action: $action, reference: $reference, timestamp: now()->getTimestamp())];
    }
}
