@component('layouts.app', [
    'title' => 'Prime Scholarship Alerts - Scholarship Alerts, Fellowships, Grants, and Funding',
    'description' => 'Search scholarships, fellowships, grants, bursaries, funded study opportunities, and scholarship alerts worldwide.',
])
    @push('head')
        @php
            $homeSchema = [
                '@'.'context' => 'https://schema.org',
                '@'.'graph' => [
                    [
                        '@'.'type' => 'Organization',
                        '@'.'id' => route('home').'#organization',
                        'name' => 'Prime Scholarship Alerts',
                        'url' => route('home'),
                        'logo' => asset('images/brand/prime-scholarship-alerts-logo.png'),
                    ],
                    [
                        '@'.'type' => 'WebSite',
                        '@'.'id' => route('home').'#website',
                        'name' => 'Prime Scholarship Alerts',
                        'alternateName' => 'Prime Scholarship',
                        'url' => route('home'),
                        'publisher' => ['@'.'id' => route('home').'#organization'],
                    ],
                    array_filter([
                        '@'.'type' => 'MobileApplication',
                        '@'.'id' => route('home').'#ios-app',
                        'name' => 'Prime Scholarship Alerts',
                        'description' => 'Discover scholarships, fellowships, grants, bursaries, and funded study opportunities from your iPhone or iPad.',
                        'applicationCategory' => 'EducationApplication',
                        'operatingSystem' => 'iOS, iPadOS',
                        'downloadUrl' => config('prime.mobile_apps.ios.url'),
                        'installUrl' => config('prime.mobile_apps.ios.url'),
                        'url' => config('prime.mobile_apps.ios.url'),
                        'publisher' => ['@'.'id' => route('home').'#organization'],
                    ], fn ($value) => $value !== null && $value !== '' && $value !== []),
                    array_filter([
                        '@'.'type' => 'MobileApplication',
                        '@'.'id' => route('home').'#android-app',
                        'name' => 'Prime Scholarship Alerts',
                        'description' => 'Discover scholarships, fellowships, grants, bursaries, and funded study opportunities from your Android device.',
                        'applicationCategory' => 'EducationApplication',
                        'operatingSystem' => 'Android',
                        'downloadUrl' => config('prime.mobile_apps.android.url'),
                        'installUrl' => config('prime.mobile_apps.android.url'),
                        'url' => config('prime.mobile_apps.android.url'),
                        'publisher' => ['@'.'id' => route('home').'#organization'],
                    ], fn ($value) => $value !== null && $value !== '' && $value !== []),
                ],
            ];
        @endphp
        <script type="application/ld+json">
            {!! json_encode($homeSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <livewire:home-scholarship-search-hero />
    <livewire:geo-personalization-banner />

    <section id="email-alerts" class="mx-auto max-w-7xl scroll-mt-24 px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
            <livewire:featured-scholarships />
            <livewire:scholarship-alert-signup />
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-6 px-4 pb-10 sm:px-6 lg:grid-cols-3 lg:px-8">
        <livewire:latest-scholarships />
        <livewire:closing-soon-scholarships />
        <livewire:fully-funded-scholarships />
    </section>

    @php
        $homeGuides = \App\Models\BlogPost::query()->published()->where('featured', true)->latest('published_at')->limit(4)->get();
    @endphp

    @if ($homeGuides->isNotEmpty())
        <section class="border-y border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Scholarship guides</p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Prepare stronger applications</h2>
                    </div>
                    <a class="text-sm font-semibold text-blue-700 hover:text-blue-900" href="{{ route('blog.index') }}">View all guides</a>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($homeGuides as $post)
                        <article class="rounded-lg border border-slate-200 p-5">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $post->category }} · {{ $post->reading_minutes }} min read</div>
                            <h3 class="mt-3 text-lg font-semibold leading-7 text-slate-950">
                                <a class="hover:text-blue-700" href="{{ route('blog.show', $post) }}">{{ $post->title }}</a>
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $post->excerpt }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @php
        $homeSearchPages = \App\Models\SeoLandingPage::query()->published()->where('featured', true)->latest('published_at')->limit(6)->get();
    @endphp

    @if ($homeSearchPages->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Popular searches</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Scholarship pages by destination and funding type</h2>
                </div>
                <a class="text-sm font-semibold text-blue-700 hover:text-blue-900" href="{{ route('seo.index') }}">View all search pages</a>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($homeSearchPages as $page)
                    <a class="rounded-lg border border-slate-200 bg-white p-5 hover:border-blue-200 hover:bg-blue-50" href="{{ route('seo.show', $page) }}">
                        <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ \Illuminate\Support\Str::headline($page->page_type) }}</span>
                        <span class="mt-3 block text-lg font-semibold leading-7 text-slate-950">{{ $page->title }}</span>
                        <span class="mt-2 block text-sm leading-6 text-slate-600">{{ $page->excerpt }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border-y border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-950">Scholarships by study level</h2>
                <div class="mt-5 grid gap-2 text-sm">
                    @foreach (\App\Models\StudyLevel::query()->active()->withCount('scholarships')->orderBy('sort_order')->limit(9)->get() as $level)
                        <a class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 hover:border-blue-200 hover:bg-blue-50" href="{{ route('scholarships.index', ['study_level' => $level->name]) }}">
                            <span>{{ $level->name }}</span>
                            <span class="text-slate-500">{{ $level->scholarships_count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-950">Scholarships by field</h2>
                <div class="mt-5 grid gap-2 text-sm">
                    @foreach (\App\Models\FieldOfStudy::query()->active()->withCount('scholarships')->orderByDesc('scholarships_count')->orderBy('name')->limit(9)->get() as $field)
                        <a class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 hover:border-blue-200 hover:bg-blue-50" href="{{ route('scholarships.index', ['field' => $field->name]) }}">
                            <span>{{ $field->name }}</span>
                            <span class="text-slate-500">{{ $field->scholarships_count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-950">Popular destinations</h2>
                <div class="mt-5 grid gap-2 text-sm">
                    @foreach (\App\Models\Country::query()->whereIn('iso2', ['GB','AU','CA','US','DE','ZA','CN','JP','NL'])->withCount('hostedScholarships')->orderBy('priority_order')->get() as $country)
                        <a class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 hover:border-blue-200 hover:bg-blue-50" href="{{ route('scholarships.index', ['host_country' => $country->iso2]) }}">
                            <span>{{ $country->name }}</span>
                            <span class="text-slate-500">{{ $country->hosted_scholarships_count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-6 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_1fr] lg:px-8">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-6">
            <h2 class="text-xl font-semibold tracking-tight text-slate-950">Verify every opportunity with the provider.</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700">Prime Scholarship Alerts is a listing board. Scholarship summaries help with discovery, but applicants must confirm requirements, deadlines, fees, and application instructions with the provider.</p>
            <a class="mt-5 inline-flex rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" href="{{ route('terms') }}">Read the listing disclaimer</a>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold tracking-tight text-slate-950">Applicant toolkit</h2>
            <p class="mt-3 text-sm leading-6 text-slate-700">Logged-in users can upload a CV with consent, receive scholarship fit suggestions, and generate editable drafts for personal statements, statements of purpose, cover letters, CV improvements, and application checklists.</p>
            <a class="mt-5 inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" href="{{ route('ai-tools') }}">Open applicant toolkit</a>
        </div>
    </section>
@endcomponent
