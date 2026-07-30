<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotificationPlatformService
{
    public function enabled(): bool
    {
        return (bool) config('services.notification_platform.enabled')
            && filled(config('services.notification_platform.url'))
            && filled(config('services.notification_platform.site'))
            && filled(config('services.notification_platform.key'));
    }

    public function publish(string $eventId, array $content, array $channels = ['push']): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $response = Http::acceptJson()->asJson()
            ->withHeaders(['X-Notification-Key' => config('services.notification_platform.key')])
            ->connectTimeout(5)
            ->timeout((int) config('services.notification_platform.timeout', 15))
            ->retry(2, 250, throw: false)
            ->post(rtrim(config('services.notification_platform.url'), '/').'/v1/internal/content-published', [
                'site' => config('services.notification_platform.site'),
                'eventId' => $eventId,
                'channels' => $channels,
                'content' => $content,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Notification platform returned HTTP '.$response->status().': '.str($response->body())->limit(500));
        }

        return true;
    }

    public function syncEmail(string $externalId, string $email, array $filters, string $status): void
    {
        if (! $this->enabled()) {
            return;
        }

        $response = Http::acceptJson()->asJson()
            ->withHeaders(['X-Notification-Key' => config('services.notification_platform.key')])
            ->connectTimeout(5)->timeout((int) config('services.notification_platform.timeout', 15))
            ->retry(2, 250, throw: false)
            ->post(rtrim(config('services.notification_platform.url'), '/').'/v1/internal/email-subscriptions/sync', [
                'site' => config('services.notification_platform.site'), 'externalId' => $externalId,
                'email' => $email, 'filters' => $filters, 'frequency' => 'immediate',
                'verified' => $status === 'active', 'status' => $status,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Notification email sync returned HTTP '.$response->status().': '.str($response->body())->limit(500));
        }
    }
}
