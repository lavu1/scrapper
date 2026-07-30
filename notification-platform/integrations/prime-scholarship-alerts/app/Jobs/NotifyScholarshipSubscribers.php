<?php

namespace App\Jobs;

use App\Mail\ScholarshipAlertDigestMail;
use App\Models\Scholarship;
use App\Services\ScholarshipAlertMatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotifyScholarshipSubscribers implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $scholarshipId) {}

    public function handle(ScholarshipAlertMatcherService $matcher): void
    {
        $scholarship = Scholarship::query()
            ->with(['provider', 'fundingType', 'eligibleCountries', 'hostCountries', 'studyLevels', 'fieldsOfStudy'])
            ->find($this->scholarshipId);

        if (! $scholarship || ! Scholarship::query()->publiclyVisible()->whereKey($scholarship->id)->exists()) {
            return;
        }

        try {
            PublishBrowserNotification::dispatch('prime-scholarship-'.$scholarship->id.'-published', [
                'id' => (string) $scholarship->id,
                'type' => 'scholarship',
                'title' => $scholarship->title,
                'description' => Str::limit(strip_tags((string) ($scholarship->summary ?: $scholarship->description)), 240),
                'url' => route('scholarships.show', $scholarship),
                'imageUrl' => $scholarship->provider?->logo_url,
                'company' => $scholarship->provider?->name,
                'country' => $scholarship->hostCountries->pluck('name')->all(),
                'studyLevel' => $scholarship->studyLevels->pluck('name')->all(),
                'field' => $scholarship->fieldsOfStudy->pluck('name')->all(),
                'funding' => $scholarship->fundingType?->name,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Could not queue browser notification; scholarship email processing will continue.', [
                'scholarship_id' => $scholarship->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $matcher->matchingSubscriptionsQuery($scholarship)
            ->chunkById(100, function (Collection $subscriptions) use ($matcher, $scholarship): void {
                foreach ($subscriptions as $subscription) {
                    if (! $matcher->matches($scholarship, $subscription)) {
                        continue;
                    }

                    $updates = [];

                    if ($subscription->wants_email && $subscription->email) {
                        try {
                            Mail::to($subscription->email)->queue(new ScholarshipAlertDigestMail($subscription, collect([$scholarship])));
                            $updates['last_sent_at'] = now();
                        } catch (\Throwable $exception) {
                            Log::warning('Scholarship alert email failed.', [
                                'subscription_id' => $subscription->id,
                                'scholarship_id' => $scholarship->id,
                                'email' => $subscription->email,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    if ($updates !== []) {
                        $subscription->forceFill($updates)->save();
                    }
                }
            });
    }
}
