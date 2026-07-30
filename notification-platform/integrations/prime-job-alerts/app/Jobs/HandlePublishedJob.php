<?php

namespace App\Jobs;

use App\Models\Job;
use App\Notifications\NewPublishedJobAlert;
use App\Services\JobAlertMatcherService;
use App\Services\SearchIndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class HandlePublishedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $jobId, public bool $notifySubscribers = true) {}

    public function handle(SearchIndexingService $indexing): void
    {
        $job = Job::query()
            ->with(['company', 'category', 'country'])
            ->find($this->jobId);

        if (! $job || ! $job->isPubliclyVisibleNow()) {
            return;
        }

        $indexing->submitUrl(route('jobs.show', $job), submitToGoogleIndexingApi: true);

        if ($this->notifySubscribers) {
            $this->queueBrowserNotification($job);
            $this->notifyMatchingSubscribers($job);
        }
    }

    private function notifyMatchingSubscribers(Job $job): void
    {
        $matcher = app(JobAlertMatcherService::class);

        $matcher->matchingSubscriptionsQuery($job)
            ->where('email_enabled', true)
            ->chunkById(100, function ($subscriptions) use ($job): void {
                $matcher = app(JobAlertMatcherService::class);

                foreach ($subscriptions as $subscription) {
                    if (! $matcher->matches($job, $subscription)) {
                        continue;
                    }

                    try {
                        Notification::route('mail', $subscription->email)
                            ->notify(new NewPublishedJobAlert($subscription, $job));

                        $subscription->forceFill(['last_sent_at' => now()])->save();
                    } catch (\Throwable $exception) {
                        Log::warning('Job alert email failed.', [
                            'subscription_id' => $subscription->id,
                            'job_id' => $job->id,
                            'email' => $subscription->email,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function queueBrowserNotification(Job $job): void
    {
        try {
            PublishBrowserNotification::dispatch('prime-job-'.$job->id.'-published', [
                'id' => (string) $job->id,
                'type' => 'job',
                'title' => $job->title,
                'description' => Str::limit(strip_tags((string) $job->description), 240),
                'url' => route('jobs.show', $job),
                'company' => $job->company?->name,
                'location' => implode(', ', array_filter([$job->city, $job->country?->name])),
                'country' => $job->country?->name ?: $job->country_code,
                'category' => $job->category?->name,
                'categoryId' => $job->category_id ? (string) $job->category_id : null,
                'jobType' => $job->work_type?->value ?? $job->work_type,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Could not queue browser notification; job publishing and email alerts will continue.', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
