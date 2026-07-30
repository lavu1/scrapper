<?php

use App\Http\Controllers\AgentDiscoveryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareerToolController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployerJobController;
use App\Http\Controllers\JobProfileController;
use App\Http\Controllers\MobileAppAssociationController;
use App\Http\Controllers\PageController;
use App\Http\Middleware\AgentDiscoveryHeaders;
use App\Http\Middleware\MarkdownForAgents;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackSiteVisit;
use App\Livewire\JobDetail;
use App\Livewire\JobSearchPage;
use App\Jobs\SyncNotificationEmailSubscription;
use App\Models\JobAlertSubscription;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$statelessPublicMiddleware = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    SetLocale::class,
    AgentDiscoveryHeaders::class,
    MarkdownForAgents::class,
    TrackSiteVisit::class,
];

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/jobs', JobSearchPage::class)->name('jobs.index');
Route::get('/jobs/{job:slug}.md', [PageController::class, 'jobMarkdown'])->name('jobs.markdown');
Route::get('/jobs/{job:slug}', JobDetail::class)->name('jobs.show');
Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/companies/{company:slug}', [CompanyController::class, 'show'])->name('companies.show');
Route::get('/countries', [PageController::class, 'countries'])->name('countries.index');
Route::get('/countries/{country:slug}', [PageController::class, 'country'])->name('countries.show');
Route::get('/career-advice', [PageController::class, 'blog'])->name('career-advice');
Route::get('/blog', [PageController::class, 'blog'])->name('blog.index');
Route::get('/blog/{post:slug}.md', [PageController::class, 'blogMarkdown'])->name('blog.markdown');
Route::get('/blog/{post:slug}', [PageController::class, 'blogShow'])->name('blog.show');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/mobile-app', [PageController::class, 'mobileApp'])->name('mobile-app');
Route::get('/post-job', [EmployerJobController::class, 'create'])->name('employer-jobs.create');
Route::post('/post-job', [EmployerJobController::class, 'store'])->name('employer-jobs.store');
Route::get('/terms', fn (PageController $controller) => $controller->static('terms'))->name('terms');
Route::get('/privacy', fn (PageController $controller) => $controller->static('privacy'))->name('privacy');
Route::get('/disclaimer', fn (PageController $controller) => $controller->static('disclaimer'))->name('disclaimer');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/docs/api', [PageController::class, 'apiDocs'])->name('docs.api');
Route::get('/robots.txt', [PageController::class, 'robots'])->withoutMiddleware($statelessPublicMiddleware)->name('robots');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->withoutMiddleware($statelessPublicMiddleware)->name('sitemap');
Route::get('/llms.txt', [PageController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [PageController::class, 'llmsFull'])->name('llms.full');
Route::get('/auth.md', [AgentDiscoveryController::class, 'authMd'])->name('agent.auth-md');
Route::withoutMiddleware($statelessPublicMiddleware)->group(function (): void {
    Route::get('/.well-known/assetlinks.json', [MobileAppAssociationController::class, 'assetLinks'])->name('mobile.assetlinks');
    Route::get('/.well-known/apple-app-site-association', [MobileAppAssociationController::class, 'appleAppSiteAssociation'])->name('mobile.aasa');
    Route::get('/apple-app-site-association', [MobileAppAssociationController::class, 'appleAppSiteAssociation'])->name('mobile.aasa-root');
});
Route::get('/.well-known/api-catalog', [AgentDiscoveryController::class, 'apiCatalog'])->name('agent.api-catalog');
Route::get('/.well-known/openapi.json', [AgentDiscoveryController::class, 'openApi'])->name('agent.openapi');
Route::get('/.well-known/oauth-authorization-server', [AgentDiscoveryController::class, 'oauthAuthorizationServer'])->name('agent.oauth-authorization-server');
Route::get('/.well-known/oauth-protected-resource', [AgentDiscoveryController::class, 'oauthProtectedResource'])->name('agent.oauth-protected-resource');
Route::get('/.well-known/jwks.json', [AgentDiscoveryController::class, 'jwks'])->name('agent.jwks');
Route::get('/.well-known/mcp/server-card.json', [AgentDiscoveryController::class, 'mcpServerCard'])->name('agent.mcp-server-card');
Route::get('/.well-known/agent-skills/index.json', [AgentDiscoveryController::class, 'agentSkillsIndex'])->name('agent.skills');
Route::get('/.well-known/agent-skills/{skill}/SKILL.md', [AgentDiscoveryController::class, 'agentSkill'])->where('skill', '[a-z0-9-]+')->name('agent.skill');
Route::get('/indexnow-key.txt', function () {
    $key = config('services.indexnow.key');

    abort_unless(config('services.indexnow.enabled') && filled($key), 404);

    return response($key, 200)->header('Content-Type', 'text/plain');
})->name('indexnow.key');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/job-profile', [JobProfileController::class, 'show'])->name('job-profile.show');
    Route::post('/job-profile/preferences', [JobProfileController::class, 'update'])->name('job-profile.update');
    Route::post('/job-profile/cv', [JobProfileController::class, 'uploadCv'])->name('job-profile.cv');
    Route::post('/job-profile/cv/create', [JobProfileController::class, 'createCv'])->name('job-profile.cv.create');
    Route::get('/jobs/{job:slug}/career-tools/{type}', [CareerToolController::class, 'show'])->name('career-tools.show');
    Route::post('/jobs/{job:slug}/career-tools/{type}', [CareerToolController::class, 'generate'])->name('career-tools.generate');
    Route::get('/api-docs', function (PageController $controller) {
        abort_unless(auth()->user()?->is_admin, 403);

        return $controller->apiDocs();
    })->name('api-docs');
});

Route::get('/alerts/{subscription}/unsubscribe/{token}', function (Request $request, JobAlertSubscription $subscription, string $token) {
    abort_unless($request->hasValidSignature() && hash_equals($subscription->verification_token, $token), 403);
    $subscription->update(['unsubscribed_at' => now(), 'email_enabled' => false, 'push_enabled' => false]);
    SyncNotificationEmailSubscription::dispatch('pja-'.$subscription->id, $subscription->email, [], 'immediate', 'unsubscribed');

    return redirect()->route('home')->with('status', 'You have been unsubscribed from this job alert.');
})->name('alerts.unsubscribe');

Route::get('/alerts/{subscription}/verify/{token}', function (Request $request, JobAlertSubscription $subscription, string $token) {
    abort_unless($request->hasValidSignature() && hash_equals($subscription->verification_token, $token), 403);
    $subscription->update(['verified_at' => now()]);
    SyncNotificationEmailSubscription::dispatch('pja-'.$subscription->id, $subscription->email, array_filter([
        'keyword' => $subscription->keyword,
        'country' => $subscription->country_code,
        'categoryId' => $subscription->category_id ? (string) $subscription->category_id : null,
    ]), $subscription->alert_frequency === 'hourly' ? 'immediate' : $subscription->alert_frequency, 'active');

    return redirect()->route('jobs.index')->with('status', 'Your job alert is active.');
})->name('alerts.verify');
