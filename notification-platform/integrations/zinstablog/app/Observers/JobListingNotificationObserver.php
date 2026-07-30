<?php

namespace App\Observers;

use App\Jobs\PublishBrowserNotification;
use App\Models\JobListing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JobListingNotificationObserver
{
    public function created(JobListing $job): void
    {
        $this->queueIfNewlyPublic($job, true);
    }

    public function updated(JobListing $job): void
    {
        $wasPublic = $job->getOriginal('status') === JobListing::STATUS_PUBLISHED
            && $job->getOriginal('published_at')
            && Carbon::parse($job->getOriginal('published_at'))->lte(now());
        $this->queueIfNewlyPublic($job, ! $wasPublic);
    }

    private function queueIfNewlyPublic(JobListing $job, bool $newlyPublic): void
    {
        if (! $newlyPublic || $job->status !== JobListing::STATUS_PUBLISHED || ! $job->published_at || $job->published_at->isFuture()) {
            return;
        }

        try {
            PublishBrowserNotification::dispatch('zinstablog-job-'.$job->id.'-published', [
                'id' => (string) $job->id,
                'type' => 'job',
                'title' => $job->title,
                'description' => Str::limit(strip_tags((string) $job->description), 240),
                'url' => route('jobs.show', $job),
                'company' => $job->company_name,
                'location' => $job->location,
                'jobType' => $job->employment_type,
            ])->afterCommit();
        } catch (\Throwable $exception) {
            Log::warning('Could not queue job browser notification; publishing and mobile notifications will continue.', ['job_id' => $job->id, 'error' => $exception->getMessage()]);
        }
    }
}
