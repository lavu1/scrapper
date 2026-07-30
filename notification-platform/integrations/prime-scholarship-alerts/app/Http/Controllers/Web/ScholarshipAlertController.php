<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncNotificationEmailSubscription;
use App\Models\ScholarshipAlertSubscription;
use Illuminate\Http\RedirectResponse;

class ScholarshipAlertController extends Controller
{
    public function confirm(ScholarshipAlertSubscription $subscription): RedirectResponse
    {
        $subscription->forceFill(['verified_at' => now()])->save();
        $this->syncCentralSubscription($subscription, 'active');

        return redirect()->route('scholarships.index')->with('status', 'Your scholarship alerts are confirmed.');
    }

    public function unsubscribe(ScholarshipAlertSubscription $subscription): RedirectResponse
    {
        $this->syncCentralSubscription($subscription, 'unsubscribed');
        $subscription->delete();

        return redirect()->route('home')->with('status', 'You have been unsubscribed from scholarship alerts.');
    }

    private function syncCentralSubscription(ScholarshipAlertSubscription $subscription, string $status): void
    {
        $subscription->loadMissing(['eligibleCountry', 'hostCountry', 'studyLevel', 'fieldOfStudy', 'fundingType']);
        SyncNotificationEmailSubscription::dispatch('psa-'.$subscription->id, $subscription->email, array_filter([
            'keyword' => $subscription->keyword,
            'country' => $subscription->hostCountry?->name,
            'studyLevel' => $subscription->studyLevel?->name,
            'field' => $subscription->fieldOfStudy?->name,
            'funding' => $subscription->fundingType?->name,
        ]), $status);
    }
}
