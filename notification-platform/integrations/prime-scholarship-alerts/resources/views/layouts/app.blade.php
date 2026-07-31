<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta name="monetag" content="2cc8f0ec563146392bc5d7b62bb8dd6e">
    <script>(function(s){s.dataset.zone='11378625',s.src='https://n6wxm.com/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Prime Scholarship Alerts' }}</title>
    <meta name="description" content="{{ $description ?? 'Find scholarships, fellowships, grants, bursaries, and funded study opportunities worldwide.' }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    @if (config('prime.mobile_apps.ios.app_id'))
        <meta name="apple-itunes-app" content="app-id={{ config('prime.mobile_apps.ios.app_id') }}">
    @endif
    <meta property="og:title" content="{{ $title ?? 'Prime Scholarship Alerts' }}">
    <meta property="og:description" content="{{ $description ?? 'Scholarship discovery board with global opportunities and optional country personalization.' }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:site_name" content="Prime Scholarship Alerts">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title ?? 'Prime Scholarship Alerts' }}">
    <meta name="twitter:description" content="{{ $description ?? 'Find scholarships, fellowships, grants, bursaries, and funded study opportunities worldwide.' }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-blue-800 focus:shadow">Skip to content</a>

    @php
        $navLinkClass = fn (bool $active): string => $active
            ? 'whitespace-nowrap rounded-md bg-blue-50 px-2.5 py-2 font-semibold text-blue-800'
            : 'whitespace-nowrap rounded-md px-2.5 py-2 hover:bg-slate-100 hover:text-blue-700';
        $mobileNavLinkClass = fn (bool $active): string => $active
            ? 'flex items-center justify-between rounded-lg bg-blue-50 px-3 py-2.5 font-semibold text-blue-800'
            : 'flex items-center justify-between rounded-lg px-3 py-2.5 hover:bg-slate-100 hover:text-blue-700';
        $navItems = [
            ['label' => 'Find', 'mobile' => 'Find', 'route' => 'scholarships.index', 'active' => request()->routeIs('scholarships.index', 'scholarships.show')],
            ['label' => 'Guides', 'mobile' => 'Guides', 'route' => 'blog.index', 'active' => request()->routeIs('blog.*')],
            ['label' => 'Search', 'mobile' => 'Search', 'route' => 'seo.index', 'active' => request()->routeIs('seo.*')],
            ['label' => 'Countries', 'mobile' => 'Countries', 'route' => 'countries.index', 'active' => request()->routeIs('countries.*')],
            ['label' => 'Providers', 'mobile' => 'Providers', 'route' => 'providers.index', 'active' => request()->routeIs('providers.*')],
            ['label' => 'Toolkit', 'mobile' => 'Toolkit', 'route' => 'ai-tools', 'active' => request()->routeIs('ai-tools')],
            ['label' => 'Post', 'mobile' => 'Post', 'route' => 'scholarships.submit', 'active' => request()->routeIs('scholarships.submit', 'scholarships.submit.*'), 'primary' => true],
        ];
        $moreActive = request()->routeIs('levels.*', 'fields.*') || request()->hasAny(['fully_funded', 'closing_in_days', 'visa_support_mentioned']);
    @endphp

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="min-w-0 flex items-center gap-3 font-semibold tracking-tight text-slate-950">
                <img src="{{ asset('images/brand/prime-scholarship-alerts-logo.png') }}" alt="Prime Scholarship Alerts logo" class="h-10 w-10 rounded-lg object-cover shadow-sm">
                <span class="truncate text-sm sm:text-base">Prime Scholarship Alerts</span>
            </a>

            <nav class="hidden flex-nowrap items-center gap-2 text-sm font-medium text-slate-700 lg:flex" aria-label="Primary">
                @foreach ($navItems as $item)
                    <a
                        class="{{ ($item['primary'] ?? false) ? ($item['active'] ? 'rounded-md bg-emerald-700 px-3 py-2 font-semibold text-white ring-2 ring-emerald-200' : 'rounded-md bg-emerald-600 px-3 py-2 font-semibold text-white hover:bg-emerald-700') : $navLinkClass($item['active']) }}"
                        href="{{ route($item['route']) }}"
                        @if ($item['active']) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endforeach
                <details class="relative">
                    <summary class="{{ $moreActive ? 'cursor-pointer list-none rounded-md bg-blue-50 px-2 py-1 font-semibold text-blue-800 [&::-webkit-details-marker]:hidden' : 'cursor-pointer list-none rounded-md px-2 py-1 hover:bg-slate-100 hover:text-blue-700 [&::-webkit-details-marker]:hidden' }}">More</summary>
                    <div class="absolute right-0 top-8 z-50 grid w-56 gap-1 rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-xl">
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('scholarships.index', ['fully_funded' => 1]) }}">Fully funded</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('scholarships.index', ['visa_support_mentioned' => 1]) }}">Visa support</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('scholarships.index', ['closing_in_days' => 14]) }}">Closing soon</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('levels.index') }}">Study levels</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('fields.index') }}">Fields of study</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('seo.index') }}">Search collections</a>
                        <a class="rounded-md px-3 py-2 hover:bg-emerald-50 hover:text-emerald-800" href="{{ route('blog.index') }}">Scholarship guides</a>
                    </div>
                </details>
                @if (auth()->user()?->is_admin)
                    <a class="hover:text-blue-700" href="{{ route('api-docs') }}">Internal API</a>
                @endif
            </nav>

            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    <a href="{{ route('saved-scholarships') }}" class="hidden rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-200 hover:text-blue-700 sm:inline-flex">Saved</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Sign in</a>
                @endauth
            </div>

            <details class="group relative lg:hidden">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:border-blue-200 hover:text-blue-700 [&::-webkit-details-marker]:hidden" aria-label="Open mobile menu">
                    <span class="sr-only">Open menu</span>
                    <span class="relative block h-5 w-5">
                        <span class="absolute left-0 top-0 block h-0.5 w-5 rounded-full bg-current transition group-open:top-2 group-open:rotate-45"></span>
                        <span class="absolute left-0 top-2 block h-0.5 w-5 rounded-full bg-current transition group-open:opacity-0"></span>
                        <span class="absolute left-0 top-4 block h-0.5 w-5 rounded-full bg-current transition group-open:top-2 group-open:-rotate-45"></span>
                    </span>
                </summary>

                <div class="absolute right-0 top-[3.25rem] z-50 w-[calc(100vw-2rem)] max-w-sm rounded-2xl border border-slate-200 bg-white p-3 text-sm font-medium text-slate-700 shadow-2xl ring-1 ring-slate-900/5">
                    <nav class="grid gap-1" aria-label="Mobile primary">
                        @foreach ($navItems as $item)
                            <a
                                class="{{ ($item['primary'] ?? false) ? ($item['active'] ? 'flex items-center justify-between rounded-lg bg-emerald-700 px-3 py-2.5 font-semibold text-white ring-2 ring-emerald-200' : 'flex items-center justify-between rounded-lg bg-emerald-600 px-3 py-2.5 font-semibold text-white hover:bg-emerald-700') : $mobileNavLinkClass($item['active']) }}"
                                href="{{ route($item['route']) }}"
                                @if ($item['active']) aria-current="page" @endif
                            >
                                <span>{{ $item['mobile'] }}</span>
                                @if ($item['active'])
                                    <span class="text-xs">Current</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>

                    <div class="my-3 border-t border-slate-100"></div>

                    <nav class="grid gap-1" aria-label="Mobile scholarship shortcuts">
                        <a class="{{ $mobileNavLinkClass(request()->boolean('fully_funded')) }}" href="{{ route('scholarships.index', ['fully_funded' => 1]) }}" @if (request()->boolean('fully_funded')) aria-current="page" @endif>Fully funded</a>
                        <a class="{{ $mobileNavLinkClass(request()->boolean('visa_support_mentioned')) }}" href="{{ route('scholarships.index', ['visa_support_mentioned' => 1]) }}" @if (request()->boolean('visa_support_mentioned')) aria-current="page" @endif>Visa support</a>
                        <a class="{{ $mobileNavLinkClass(request('closing_in_days') === '14') }}" href="{{ route('scholarships.index', ['closing_in_days' => 14]) }}" @if (request('closing_in_days') === '14') aria-current="page" @endif>Closing soon</a>
                        <a class="{{ $mobileNavLinkClass(request()->routeIs('levels.*')) }}" href="{{ route('levels.index') }}" @if (request()->routeIs('levels.*')) aria-current="page" @endif>Study levels</a>
                        <a class="{{ $mobileNavLinkClass(request()->routeIs('fields.*')) }}" href="{{ route('fields.index') }}" @if (request()->routeIs('fields.*')) aria-current="page" @endif>Fields of study</a>
                        @if (auth()->user()?->is_admin)
                            <a class="{{ $mobileNavLinkClass(request()->routeIs('api-docs')) }}" href="{{ route('api-docs') }}" @if (request()->routeIs('api-docs')) aria-current="page" @endif>Internal API</a>
                        @endif
                    </nav>

                    <div class="my-3 border-t border-slate-100"></div>

                    <div class="grid gap-2">
                        @auth
                            <a href="{{ route('saved-scholarships') }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-center font-semibold text-slate-800 hover:border-blue-200 hover:text-blue-700">Saved scholarships</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="w-full rounded-lg bg-slate-900 px-3 py-2.5 font-semibold text-white hover:bg-blue-800">Sign out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg bg-blue-700 px-3 py-2.5 text-center font-semibold text-white hover:bg-blue-800">Sign in</a>
                        @endauth
                    </div>
                </div>
            </details>
        </div>
    </header>

    <x-app-download-banner />

    @if (session('status'))
        <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <div class="mx-auto max-w-7xl">{{ session('status') }}</div>
        </div>
    @endif

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 text-sm text-slate-600 sm:px-6 md:grid-cols-4 lg:px-8">
            <div>
                <div class="font-semibold text-slate-950">Prime Scholarship Alerts</div>
                <p class="mt-3 leading-6">A scholarship listing board for discovering funding opportunities worldwide. Verify details and apply through official providers.</p>
            </div>
            <div>
                <div class="font-semibold text-slate-950">Explore</div>
                <div class="mt-3 grid gap-2">
                    <a href="{{ route('scholarships.index') }}">Find scholarships</a>
                    <a href="{{ route('seo.index') }}">Search collections</a>
                    <a href="{{ route('blog.index') }}">Scholarship guides</a>
                    <a href="{{ route('countries.index') }}">Countries</a>
                    <a href="{{ route('levels.index') }}">Study levels</a>
                    <a href="{{ route('fields.index') }}">Fields of study</a>
                </div>
            </div>
            <div>
                <div class="font-semibold text-slate-950">Resources</div>
                <div class="mt-3 grid gap-2">
                    <a href="{{ route('blog.index') }}">Study tips</a>
                    <a href="{{ route('ai-tools') }}">Applicant toolkit</a>
                    @if (config('prime.mobile_apps.ios.url'))
                        <a href="{{ config('prime.mobile_apps.ios.url') }}" rel="noopener external" target="_blank">Download the iPhone app</a>
                    @endif
                    @if (config('prime.mobile_apps.android.url'))
                        <a href="{{ config('prime.mobile_apps.android.url') }}" rel="noopener external" target="_blank">Download the Android app</a>
                    @endif
                    <a href="{{ route('contact') }}">Contact</a>
                    @if (auth()->user()?->is_admin)
                        <a href="{{ route('api-docs') }}">Internal API docs</a>
                    @endif
                </div>
            </div>
            <div>
                <div class="font-semibold text-slate-950">Legal</div>
                <div class="mt-3 grid gap-2">
                    <a href="{{ route('terms') }}">Terms</a>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <a href="{{ route('account.delete') }}">Delete account</a>
                    <a href="{{ route('disclaimer') }}">Disclaimer</a>
                    <a href="{{ route('contact') }}">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <x-alert-bell />

    @livewireScripts
    @if (config('services.notification_platform.enabled'))
        <script src="{{ rtrim(config('services.notification_platform.url'), '/') }}/assets/client.js" data-site="{{ config('services.notification_platform.site') }}" data-api="{{ rtrim(config('services.notification_platform.url'), '/') }}" defer></script>
    @endif
</body>
</html>
