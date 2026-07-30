<?php

namespace App\Jobs;

use App\Services\NotificationPlatformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishBrowserNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public string $eventId, public array $content) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(NotificationPlatformService $notifications): void
    {
        $notifications->publish($this->eventId, $this->content, ['push']);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Browser notification permanently failed without affecting the publishing workflow.', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}
