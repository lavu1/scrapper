<?php

namespace App\Jobs;

use App\Services\NotificationPlatformService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncNotificationEmailSubscription implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public string $externalId, public string $email, public array $filters, public string $frequency, public string $status) {}

    public function backoff(): array { return [30, 120, 600]; }

    public function handle(NotificationPlatformService $notifications): void
    {
        $notifications->syncEmail($this->externalId, $this->email, $this->filters, $this->frequency, $this->status);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Central email subscription sync failed; local email alerts remain active.', ['external_id' => $this->externalId, 'error' => $exception->getMessage()]);
    }
}
