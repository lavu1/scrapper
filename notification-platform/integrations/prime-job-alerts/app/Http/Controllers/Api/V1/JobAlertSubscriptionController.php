<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobAlertSubscriptionRequest;
use App\Http\Resources\Api\V1\JobAlertSubscriptionResource;
use App\Jobs\SyncNotificationEmailSubscription;
use App\Models\Category;
use App\Models\JobAlertSubscription;
use App\Notifications\VerifyJobAlertSubscription;
use App\Services\CountryResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobAlertSubscriptionController extends Controller
{
    public function store(JobAlertSubscriptionRequest $request, CountryResolverService $resolver)
    {
        $data = $request->validated();
        $countryCode = $this->resolveCountryCode($data, $resolver);
        $category = $this->resolveCategory($data);
        $email = Str::lower(trim((string) $data['email']));
        $keyword = filled($data['keyword'] ?? null) ? trim((string) $data['keyword']) : null;

        $subscription = JobAlertSubscription::query()->firstOrNew([
            'email' => $email,
            'keyword' => $keyword,
            'country_code' => $countryCode,
            'category_id' => $category?->id,
        ]);

        $needsVerification = ! $subscription->exists || $subscription->unsubscribed_at !== null || $subscription->verified_at === null;

        if (! $subscription->exists || ! $subscription->verification_token) {
            $subscription->verification_token = Str::random(64);
        }

        if ($subscription->unsubscribed_at !== null) {
            $subscription->verified_at = null;
            $subscription->verification_token = Str::random(64);
        }

        $subscription->forceFill([
            'user_id' => auth()->id(),
            'email' => $email,
            'keyword' => $keyword,
            'country_code' => $countryCode,
            'category_id' => $category?->id,
            'alert_frequency' => $data['alert_frequency'] ?? 'daily',
            'email_enabled' => $request->boolean('email_enabled', true),
            'push_enabled' => $request->boolean('push_enabled') && filled($data['webpushr_subscriber_id'] ?? null),
            'webpushr_subscriber_id' => $data['webpushr_subscriber_id'] ?? null,
            'unsubscribed_at' => null,
        ])->save();

        $subscription->load(['category', 'country']);
        $this->syncCentralSubscription($subscription, $subscription->verified_at ? 'active' : 'pending');

        if ($needsVerification && $subscription->email_enabled) {
            Notification::route('mail', $subscription->email)->notify(new VerifyJobAlertSubscription($subscription));
        }

        return $this->envelope(
            (new JobAlertSubscriptionResource($subscription))->resolve(),
            ['verification_required' => $subscription->verified_at === null],
            $this->subscriptionLinks($subscription),
            $subscription->verified_at
                ? 'Job alert subscription is active.'
                : 'Job alert created. Check your email to confirm it before alerts are sent.',
            $subscription->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function show(Request $request, JobAlertSubscription $subscription)
    {
        $this->authorizeTokenOrSignature($request, $subscription);

        $subscription->load(['category', 'country']);

        return $this->envelope(
            (new JobAlertSubscriptionResource($subscription))->resolve(),
            [],
            $this->subscriptionLinks($subscription),
            'Job alert subscription fetched.',
        );
    }

    public function verify(Request $request, JobAlertSubscription $subscription)
    {
        $this->authorizeTokenOrSignature($request, $subscription);

        $subscription->forceFill([
            'verified_at' => $subscription->verified_at ?: now(),
            'unsubscribed_at' => null,
            'email_enabled' => true,
        ])->save();

        $subscription->load(['category', 'country']);
        $this->syncCentralSubscription($subscription, 'active');

        return $this->envelope(
            (new JobAlertSubscriptionResource($subscription))->resolve(),
            [],
            $this->subscriptionLinks($subscription),
            'Job alert subscription verified. New matching jobs will be emailed as they are published.',
        );
    }

    public function destroy(Request $request, JobAlertSubscription $subscription)
    {
        $this->authorizeTokenOrSignature($request, $subscription);

        $subscription->forceFill([
            'unsubscribed_at' => now(),
            'email_enabled' => false,
            'push_enabled' => false,
        ])->save();

        $subscription->load(['category', 'country']);
        $this->syncCentralSubscription($subscription, 'unsubscribed');

        return $this->envelope(
            (new JobAlertSubscriptionResource($subscription))->resolve(),
            [],
            [],
            'You have been unsubscribed from this job alert.',
        );
    }

    private function resolveCountryCode(array $data, CountryResolverService $resolver): ?string
    {
        $input = $data['country_code'] ?? $data['country'] ?? null;
        $countryCode = $resolver->normalizeCountryInput($input);

        if ($countryCode === 'ALL') {
            return null;
        }

        if ($input && ! $countryCode) {
            throw ValidationException::withMessages([
                'country' => 'Country not found. Use a valid ISO2 code, ISO3 code, country slug, country name, or ALL.',
            ]);
        }

        return $countryCode;
    }

    private function syncCentralSubscription(JobAlertSubscription $subscription, string $status): void
    {
        SyncNotificationEmailSubscription::dispatch(
            'pja-'.$subscription->id,
            $subscription->email,
            array_filter([
                'keyword' => $subscription->keyword,
                'country' => $subscription->country_code,
                'categoryId' => $subscription->category_id ? (string) $subscription->category_id : null,
            ]),
            $subscription->alert_frequency === 'hourly' ? 'immediate' : $subscription->alert_frequency,
            $status,
        );
    }

    private function resolveCategory(array $data): ?Category
    {
        if (! empty($data['category_id'])) {
            return Category::query()->where('active', true)->findOrFail($data['category_id']);
        }

        $input = trim((string) ($data['category_slug'] ?? $data['category'] ?? ''));
        if ($input === '') {
            return null;
        }

        $slug = Str::slug($input);
        $category = Category::query()
            ->where('active', true)
            ->where(fn ($query) => $query
                ->where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [Str::lower($input)]))
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category' => 'Category not found. Use a valid category id, slug, or name.',
            ]);
        }

        return $category;
    }

    private function authorizeTokenOrSignature(Request $request, JobAlertSubscription $subscription): void
    {
        abort_unless(
            $request->hasValidSignature() || hash_equals($subscription->verification_token, (string) $request->query('token', '')),
            403,
            'A valid alert token is required.',
        );
    }

    private function subscriptionLinks(JobAlertSubscription $subscription): array
    {
        return [
            'verify' => URL::signedRoute('alerts.verify', [
                'subscription' => $subscription->id,
                'token' => $subscription->verification_token,
            ]),
            'unsubscribe' => URL::signedRoute('alerts.unsubscribe', [
                'subscription' => $subscription->id,
                'token' => $subscription->verification_token,
            ]),
            'api_status' => URL::temporarySignedRoute('api.v1.job-alerts.show', now()->addYear(), [
                'subscription' => $subscription->id,
            ]),
            'api_verify' => URL::temporarySignedRoute('api.v1.job-alerts.verify', now()->addYear(), [
                'subscription' => $subscription->id,
            ]),
            'api_unsubscribe' => URL::temporarySignedRoute('api.v1.job-alerts.destroy', now()->addYear(), [
                'subscription' => $subscription->id,
            ]),
        ];
    }
}
