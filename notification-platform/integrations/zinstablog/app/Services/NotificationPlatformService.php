<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotificationPlatformService
{
    public function publish(string $eventId, array $content): bool
    {
        if (! config('services.notification_platform.enabled')) {
            return false;
        }

        $response = Http::acceptJson()->asJson()
            ->withHeaders(['X-Notification-Key' => config('services.notification_platform.key')])
            ->connectTimeout(5)->timeout((int) config('services.notification_platform.timeout', 15))
            ->retry(2, 250, throw: false)
            ->post(rtrim(config('services.notification_platform.url'), '/').'/v1/internal/content-published', [
                'site' => config('services.notification_platform.site'),
                'eventId' => $eventId,
                'channels' => ['push'],
                'content' => $content,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Notification platform returned HTTP '.$response->status().': '.str($response->body())->limit(500));
        }

        return true;
    }
}
