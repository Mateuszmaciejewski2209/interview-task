<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Exception;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final class RecordingNotificationFacade implements NotificationFacadeInterface
{
    /** @var list<NotifyData> */
    public private(set) array $sent = [];

    private ?Exception $failure = null;

    public function notify(NotifyData $data): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->sent[] = $data;
    }

    public function failWith(Exception $failure): void
    {
        $this->failure = $failure;
    }

    public function recover(): void
    {
        $this->failure = null;
    }
}
