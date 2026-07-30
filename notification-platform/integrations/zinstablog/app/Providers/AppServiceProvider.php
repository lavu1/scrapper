<?php

namespace App\Providers;

use App\Models\ElectionCandidate;
use App\Models\ElectionResult;
use App\Models\JobListing;
use App\Models\Post;
use App\Observers\ElectionCandidateObserver;
use App\Observers\ElectionResultObserver;
use App\Observers\JobListingNotificationObserver;
use App\Observers\PostNotificationObserver;
use App\Services\CareerGuideService;
use App\Services\TrendPageService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        ElectionCandidate::observe(ElectionCandidateObserver::class);
        ElectionResult::observe(ElectionResultObserver::class);
        JobListing::observe(JobListingNotificationObserver::class);
        Post::observe(PostNotificationObserver::class);

        View::composer(['partials.sidebar', 'partials.footer'], function ($view): void {
            $view->with('trendPages', app(TrendPageService::class)->featured());
            $view->with('careerGuides', app(CareerGuideService::class)->featured());
        });
    }
}
