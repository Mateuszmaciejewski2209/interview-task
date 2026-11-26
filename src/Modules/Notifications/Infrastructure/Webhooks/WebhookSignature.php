<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Webhooks;

use RuntimeException;
use SensitiveParameter;

/**
 * HMAC-SHA256 of "<timestamp>.<action>.<reference>", sent as `t=<unix>,v1=<hex>`.
 * A replay inside the tolerance window is harmless, confirming a delivery is idempotent.
 */
final readonly class WebhookSignature
{
    public function __construct(
        #[SensitiveParameter] private string $secret,
        private int $toleranceSeconds = 300,
    ) {}

    /** Used by the tests and the simulate-delivered command. */
    public function header(string $action, string $reference, int $timestamp): string
    {
        return "t={$timestamp},v1=".$this->hmac(timestamp: $timestamp, action: $action, reference: $reference);
    }

    public function verify(?string $header, string $action, string $reference, int $now): bool
    {
        if ($header === null || preg_match('/^t=(\d{1,12}),v1=([0-9a-f]{64})$/D', $header, $parts) !== 1) {
            return false;
        }
        $timestamp = (int) $parts[1];
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->hmac(timestamp: $timestamp, action: $action, reference: $reference), $parts[2]);
    }

    /**
     * The secret is checked here and not in the constructor, because middleware is
     * also built in terminate(), where the exception wouldn't be handled.
     */
    private function hmac(int $timestamp, string $action, string $reference): string
    {
        if ($this->secret === '') {
            throw new RuntimeException('The notification webhook secret is not configured (NOTIFICATION_WEBHOOK_SECRET).');
        }

        return hash_hmac('sha256', "{$timestamp}.{$action}.{$reference}", $this->secret);
    }
}
