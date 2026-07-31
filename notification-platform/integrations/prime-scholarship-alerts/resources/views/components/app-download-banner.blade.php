@php
    $iosAppUrl = config('prime.mobile_apps.ios.url');
    $androidAppUrl = config('prime.mobile_apps.android.url');
@endphp

@if ($iosAppUrl || $androidAppUrl)
    <aside class="border-b border-blue-950 bg-gradient-to-r from-slate-950 via-blue-950 to-slate-950 text-white" aria-label="Prime Scholarship Alerts mobile apps" data-app-download-banner>
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div class="flex min-w-0 items-start gap-3 sm:items-center">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current" stroke-width="1.8">
                        <rect x="7" y="2.5" width="10" height="19" rx="2"></rect>
                        <path d="M10 5h4M11 18.5h2"></path>
                    </svg>
                </span>
                <div>
                    <p class="font-semibold tracking-tight">Prime Scholarship Alerts is available for Android, iPhone, and iPad.</p>
                    <p class="mt-1 text-sm leading-6 text-blue-100">Download the app and browse current scholarship opportunities wherever you are.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">
                <a
                    class="inline-flex min-h-12 items-center justify-center gap-3 rounded-xl bg-white px-4 py-2 text-left text-slate-950 shadow-sm hover:bg-blue-50"
                    href="{{ $iosAppUrl }}"
                    target="_blank"
                    rel="noopener external"
                    aria-label="Download Prime Scholarship Alerts on the App Store"
                >
                    <svg viewBox="0 0 24 24" class="h-6 w-6 fill-current" aria-hidden="true">
                        <path d="M16.7 12.9c0-2.5 2-3.7 2.1-3.8-1.2-1.7-3-1.9-3.7-1.9-1.6-.2-3.1.9-3.9.9-.8 0-2-1-3.4-.9-1.7 0-3.4 1-4.3 2.6-1.9 3.2-.5 8 1.3 10.6.9 1.3 2 2.7 3.4 2.6 1.3-.1 1.9-.9 3.5-.9s2.1.9 3.6.8c1.5 0 2.4-1.3 3.3-2.6 1-1.5 1.5-3 1.5-3.1-.1 0-3.4-1.3-3.4-4.3ZM14 5.6c.7-.9 1.2-2.1 1.1-3.3-1 .1-2.3.7-3 1.6-.7.8-1.3 2-1.1 3.2 1.1.1 2.3-.6 3-1.5Z"></path>
                    </svg>
                    <span>
                        <span class="block text-[0.65rem] font-medium uppercase tracking-wide text-slate-600">Download on the</span>
                        <span class="block text-base font-semibold leading-5">App Store</span>
                    </span>
                </a>

                @if ($androidAppUrl)
                    <a
                        class="inline-flex min-h-12 items-center justify-center gap-3 rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-left text-white hover:bg-white/20"
                        href="{{ $androidAppUrl }}"
                        target="_blank"
                        rel="noopener external"
                        aria-label="Download Prime Scholarship Alerts on Google Play"
                    >
                        <svg viewBox="0 0 24 24" class="h-6 w-6 fill-current" aria-hidden="true">
                            <path d="M3.6 2.4c-.3.3-.5.8-.5 1.4v16.4c0 .6.2 1.1.5 1.4l.1.1 9.2-9.2v-.2L3.7 2.3l-.1.1Zm12.4 13.2-3-3-8.9 8.9c.5.4 1.2.4 1.9 0l10-5.9Zm3.1-4.9-2.1-1.2-3.3 3.3 3.3 3.3 2.2-1.3c1.2-.7 1.2-1.8-.1-2.5v-1.6ZM4.1 2.5l8.9 8.9 3-3-10-5.9c-.7-.4-1.4-.4-1.9 0Z"></path>
                        </svg>
                        <span>
                            <span class="block text-[0.65rem] font-medium uppercase tracking-wide text-blue-100">Get it on</span>
                            <span class="block text-base font-semibold leading-5">Google Play</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    </aside>
@endif
