<details class="fixed bottom-4 right-4 z-50">
    <summary class="flex h-12 w-12 cursor-pointer list-none items-center justify-center rounded-full bg-emerald-600 text-white shadow-xl shadow-slate-900/20 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 [&::-webkit-details-marker]:hidden">
        <span class="sr-only">Open notification options</span>
        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.268 21a2 2 0 0 0 3.464 0" />
            <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />
        </svg>
    </summary>

    <div class="absolute bottom-16 right-0 w-[min(92vw,330px)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
        <div class="border-b border-slate-100 px-4 py-3">
            <div class="text-sm font-semibold text-slate-950">Notifications</div>
            <p class="mt-1 text-xs leading-5 text-slate-500">Choose browser push, email alerts, or both.</p>
        </div>

        <div class="grid gap-3 p-3">
            <section class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.268 21a2 2 0 0 0 3.464 0" />
                            <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-emerald-950">Browser push</h2>
                        <p class="mt-1 text-xs leading-5 text-emerald-900">Quick alerts when new scholarships are published.</p>
                    </div>
                </div>

                @if (config('services.notification_platform.enabled'))
                    <button
                        type="button"
                        data-enable-notifications
                        data-enabled-label="Notifications enabled"
                        class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        Enable notifications
                    </button>
                    <p data-custom-push-status class="mt-2 rounded-md bg-white/70 px-2.5 py-2 text-xs leading-5 text-emerald-900" aria-live="polite">
                        Click enable, then allow notifications in your browser.
                    </p>
                @else
                    <button type="button" disabled class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-md bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500">
                        Push unavailable
                    </button>
                    <p class="mt-2 rounded-md bg-white/70 px-2.5 py-2 text-xs leading-5 text-slate-500">
                        Browser push is not configured yet.
                    </p>
                @endif
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7" />
                            <rect x="2" y="4" width="20" height="16" rx="2" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-950">Email alerts</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Get emails for new scholarships you want to follow.</p>
                    </div>
                </div>
                <div class="mt-3">
                    <livewire:scholarship-alert-signup :compact="true" :quick="true" :key="'floating-email-alert-bell'" />
                </div>
                <a href="{{ route('home') }}#email-alerts" class="mt-3 inline-flex text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                    Open detailed email filters
                </a>
            </section>
        </div>
    </div>
</details>
