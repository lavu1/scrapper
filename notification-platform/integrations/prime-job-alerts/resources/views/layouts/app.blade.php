@props([
    'title' => 'Prime Job Alerts',
    'description' => 'Find verified job alerts by country, city, category, or remote type.',
    'markdownUrl' => null,
    'useMarketBrand' => true,
    'ogType' => 'website',
    'publishedTime' => null,
    'modifiedTime' => null,
])
@php
    $resolver = app(\App\Services\CountryResolverService::class);
    $brand = app(\App\Services\BrandNameService::class);
    $marketCountryCode = $resolver->normalizeCountryInput((string) (request('country') ?: request()->cookie('prima_country') ?: session('selected_country', '')));
    $marketCountry = $marketCountryCode && $marketCountryCode !== 'ALL'
        ? \App\Models\Country::query()->where('iso2', $marketCountryCode)->first()
        : null;
    $brandLabel = $brand->marketName($marketCountry, $marketCountryCode === 'ALL');
    $supportedLocales = config('prima.supported_locales', ['en' => 'English']);
    $rtl = in_array(app()->getLocale(), config('prima.rtl_locales', []), true);
    $hasJobSearchFilters = request()->routeIs('jobs.index')
        && collect(request()->query())
            ->except('lang')
            ->contains(fn ($value) => ! in_array($value, [null, '', false], true));
    $trackingParameters = [
        'app_prompt', 'dclid', 'fbclid', 'gad_campaignid', 'gad_source', 'gclid', 'gbraid',
        'mc_cid', 'mc_eid', 'msclkid', 'ref', 'wbraid', '_ga',
    ];
    $canonicalQuery = collect(request()->query())
        ->reject(function ($value, $key) use ($trackingParameters) {
            $normalizedKey = strtolower((string) $key);

            return $normalizedKey === 'lang'
                || str_starts_with($normalizedKey, 'utm_')
                || in_array($normalizedKey, $trackingParameters, true)
                || in_array($value, [null, '', false], true);
        })
        ->all();

    if ($hasJobSearchFilters) {
        $canonicalQuery = [];
    }

    ksort($canonicalQuery);
    $preferredOrigin = rtrim((string) config('app.url'), '/');
    $canonicalPath = request()->routeIs('career-advice')
        ? (parse_url(route('blog.index'), PHP_URL_PATH) ?: '/blog')
        : request()->getPathInfo();
    $canonical = $preferredOrigin
        .($canonicalPath === '/' ? '' : $canonicalPath)
        .($canonicalQuery === [] ? '' : '?'.http_build_query($canonicalQuery, '', '&', PHP_QUERY_RFC3986));
    $robotsContent = $hasJobSearchFilters
        ? 'noindex,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'
        : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
    $titleBrand = $useMarketBrand ? $brandLabel : $brand->siteName();
    $pageTitle = $title === $brand->siteName() ? $titleBrand : $title.' | '.$titleBrand;
    $playStoreUrl = config('mobile.android.play_store_url');
    $appStoreUrl = config('mobile.ios.app_store_url');
    $appStoreId = config('mobile.ios.app_store_id');
    $navItems = [
        ['label' => __('Find Jobs'), 'url' => route('jobs.index'), 'active' => request()->routeIs('jobs.*')],
        ['label' => __('Companies'), 'url' => route('companies.index'), 'active' => request()->routeIs('companies.*')],
        ['label' => __('Career Advice'), 'url' => route('career-advice'), 'active' => request()->routeIs('career-advice')],
        ['label' => __('Blog'), 'url' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
        ['label' => __('Countries'), 'url' => route('countries.index'), 'active' => request()->routeIs('countries.*')],
        ['label' => __('About'), 'url' => route('about'), 'active' => request()->routeIs('about')],
        ['label' => __('Post a Job'), 'url' => route('employer-jobs.create'), 'active' => request()->routeIs('employer-jobs.*')],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta name="monetag" content="c860e2acdd8a0dd1fe4405012b10ba45">
    <script>(function(s){s.dataset.zone='11378662',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robotsContent }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if ($appStoreId)
        <meta name="apple-itunes-app" content="app-id={{ $appStoreId }}, app-argument={{ $canonical }}">
    @endif
    <link rel="alternate" type="text/markdown" href="{{ route('llms') }}" title="Prime Job Alerts AI index">
    @if ($markdownUrl)
        <link rel="alternate" type="text/markdown" href="{{ $markdownUrl }}" title="{{ $pageTitle }} markdown">
    @endif
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($ogType === 'article' && $publishedTime)
        <meta property="article:published_time" content="{{ $publishedTime }}">
    @endif
    @if ($ogType === 'article' && $modifiedTime)
        <meta property="article:modified_time" content="{{ $modifiedTime }}">
    @endif
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('components.notifications')
</head>
<body>
    <div class="min-h-screen">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <nav class="mx-auto flex h-16 max-w-[1440px] items-center gap-2 px-4 sm:gap-3 sm:px-6 xl:gap-4 xl:px-8" aria-label="Main navigation">
                <a href="{{ route('home') }}" class="min-w-0 flex-1 xl:max-w-52 xl:flex-none">
                    <x-prima-logo :label="$brandLabel" :show-tagline="false" mark-class="h-9 w-9" text-class="text-base" />
                </a>
                <div class="hidden min-w-0 flex-1 items-center justify-center gap-0.5 text-sm font-semibold text-slate-600 xl:flex">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @class([
                                'shrink-0 whitespace-nowrap rounded-lg px-2.5 py-2 leading-none transition',
                                'bg-prima-50 text-prima-900 ring-1 ring-inset ring-prima-100' => $item['active'],
                                'hover:bg-slate-100 hover:text-prima-900' => ! $item['active'],
                            ])
                            @if ($item['active']) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @endforeach
                    @auth
                        <a
                            href="{{ route('job-profile.show') }}"
                            @class([
                                'shrink-0 whitespace-nowrap rounded-lg px-2.5 py-2 leading-none transition',
                                'bg-prima-50 text-prima-900 ring-1 ring-inset ring-prima-100' => request()->routeIs('job-profile.*'),
                                'hover:bg-slate-100 hover:text-prima-900' => ! request()->routeIs('job-profile.*'),
                            ])
                            @if (request()->routeIs('job-profile.*')) aria-current="page" @endif
                        >{{ __('Job Profile') }}</a>
                    @endauth
                </div>
                <div class="ml-auto hidden shrink-0 items-center gap-1.5 xl:flex">
                    @if ($playStoreUrl || $appStoreUrl)
                        <a href="{{ route('mobile-app') }}" class="inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-prima-900 px-4 text-sm font-bold text-white shadow-sm shadow-prima-900/10 transition hover:bg-prima-700" aria-label="Download the Prime Jobs Global app for Android or iPhone">
                            <x-heroicon-o-device-phone-mobile class="h-4.5 w-4.5" />
                            Get the app
                        </a>
                    @endif
                    <label class="sr-only" for="locale-switcher">{{ __('Language') }}</label>
                    <select id="locale-switcher" class="h-10 max-w-28 rounded-full border border-slate-200 bg-white px-3 pr-8 text-sm font-semibold text-slate-700" onchange="if (this.value) window.location.href = this.value">
                        @foreach ($supportedLocales as $localeCode => $localeName)
                            <option value="{{ request()->fullUrlWithQuery(['lang' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>{{ $localeName }}</option>
                        @endforeach
                    </select>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="inline-flex h-10 shrink-0 items-center whitespace-nowrap rounded-full px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Sign out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-10 shrink-0 items-center whitespace-nowrap rounded-full px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Sign in') }}</a>
                    @endauth
                </div>
                @if ($playStoreUrl || $appStoreUrl)
                    <a href="{{ route('mobile-app') }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full bg-prima-900 px-3 text-sm font-bold text-white shadow-sm hover:bg-prima-700 xl:hidden" aria-label="Download the Prime Jobs Global app for Android or iPhone">
                        <x-heroicon-o-device-phone-mobile class="h-5 w-5" />
                        <span class="hidden sm:inline">Get app</span>
                    </a>
                @endif
                <details class="relative xl:hidden">
                    <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border border-slate-200 bg-white text-prima-900 marker:hidden hover:bg-slate-50 [&::-webkit-details-marker]:hidden" aria-label="{{ __('Open menu') }}">
                        <x-heroicon-o-bars-3 class="h-6 w-6" />
                    </summary>
                    <div class="absolute right-0 mt-3 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-lg border border-slate-200 bg-white p-2 text-sm font-semibold text-slate-700 shadow-xl">
                        <div class="grid gap-1">
                            @foreach ($navItems as $item)
                                <a
                                    href="{{ $item['url'] }}"
                                    @class([
                                        'rounded-lg px-3 py-2.5 transition',
                                        'bg-prima-900 text-white shadow-sm shadow-prima-900/10' => $item['active'],
                                        'hover:bg-slate-100 hover:text-prima-700' => ! $item['active'],
                                    ])
                                    @if ($item['active']) aria-current="page" @endif
                                >{{ $item['label'] }}</a>
                            @endforeach
                            @auth
                                <a
                                    href="{{ route('job-profile.show') }}"
                                    @class([
                                        'rounded-lg px-3 py-2.5 transition',
                                        'bg-prima-900 text-white shadow-sm shadow-prima-900/10' => request()->routeIs('job-profile.*'),
                                        'hover:bg-slate-100 hover:text-prima-700' => ! request()->routeIs('job-profile.*'),
                                    ])
                                    @if (request()->routeIs('job-profile.*')) aria-current="page" @endif
                                >{{ __('Job Profile') }}</a>
                            @endauth
                        </div>
                        <div class="mt-2 border-t border-slate-200 pt-2">
                            @if ($playStoreUrl)
                                <a href="{{ $playStoreUrl }}" class="mb-1 flex items-center justify-between rounded-lg bg-prima-50 px-3 py-2.5 text-prima-900">
                                    <span class="inline-flex items-center gap-2"><x-heroicon-o-device-phone-mobile class="h-5 w-5" /> Get Android app</span>
                                    <span class="text-xs text-emerald-700">Free</span>
                                </a>
                            @endif
                            @if ($appStoreUrl)
                                <a href="{{ $appStoreUrl }}" class="mb-2 flex items-center justify-between rounded-lg bg-prima-50 px-3 py-2.5 text-prima-900">
                                    <span class="inline-flex items-center gap-2"><x-heroicon-o-device-phone-mobile class="h-5 w-5" /> Get iPhone app</span>
                                    <span class="text-xs text-emerald-700">Free</span>
                                </a>
                            @endif
                            <label class="sr-only" for="mobile-locale-switcher">{{ __('Language') }}</label>
                            <select id="mobile-locale-switcher" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold text-slate-700" onchange="if (this.value) window.location.href = this.value">
                                @foreach ($supportedLocales as $localeCode => $localeName)
                                    <option value="{{ request()->fullUrlWithQuery(['lang' => $localeCode]) }}" @selected(app()->getLocale() === $localeCode)>{{ $localeName }}</option>
                                @endforeach
                            </select>
                            @auth
                                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                                    @csrf
                                    <button class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Sign out') }}</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="mt-2 block rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Sign in') }}</a>
                            @endauth
                        </div>
                    </div>
                </details>
            </nav>
        </header>

        @if (session('status'))
            <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-900">{{ session('status') }}</div>
        @endif

        <main>
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="mt-16 border-t border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
                <div class="md:col-span-2">
                    <x-prima-logo :label="$brandLabel" mark-class="h-10 w-10" text-class="text-base" />
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600">{{ __('Powered by Prime Job Alerts for country-specific job discovery. Applications happen through employers or original sources.') }}</p>
                    @if ($playStoreUrl || $appStoreUrl)
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            @if ($playStoreUrl)
                                <a href="{{ $playStoreUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-prima-900 px-4 py-2.5 text-sm font-black text-white hover:bg-prima-700">
                                    <x-heroicon-o-device-phone-mobile class="h-5 w-5" />
                                    Get it on Google Play
                                </a>
                            @endif
                            @if ($appStoreUrl)
                                <a href="{{ $appStoreUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-prima-200 px-4 py-2.5 text-sm font-black text-prima-900 hover:bg-prima-50">
                                    <x-heroicon-o-device-phone-mobile class="h-5 w-5" />
                                    Download on the App Store
                                </a>
                            @endif
                            <a href="{{ route('mobile-app') }}" class="text-sm font-bold text-prima-700">App details</a>
                        </div>
                    @endif
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-900">{{ __('Explore') }}</div>
                    <div class="mt-3 grid gap-2 text-sm text-slate-600">
                        <a href="{{ route('jobs.index') }}">{{ __('Find Jobs') }}</a>
                        <a href="{{ route('companies.index') }}">{{ __('Companies') }}</a>
                        <a href="{{ route('countries.index') }}">{{ __('Countries') }}</a>
                        <a href="{{ route('blog.index') }}">{{ __('Career Blog') }}</a>
                        <a href="{{ route('about') }}">{{ __('About') }}</a>
                    </div>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-900">{{ __('Legal') }}</div>
                    <div class="mt-3 grid gap-2 text-sm text-slate-600">
                        <a href="{{ route('terms') }}">{{ __('Terms') }}</a>
                        <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a>
                        <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
                    </div>
                </div>
            </div>
        </footer>
        @include('components.job-alert-floating-bell')
        @include('components.mobile-app-promotion')
    </div>
    @livewireScripts
</body>
</html>
