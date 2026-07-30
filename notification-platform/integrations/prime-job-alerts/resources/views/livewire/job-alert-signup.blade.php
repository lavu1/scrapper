<div class="{{ $compact ? 'rounded-lg bg-white p-4' : 'rounded-lg border border-slate-200 bg-white p-6' }}">
    @php($browserPushEnabled = (bool) config('services.notification_platform.enabled'))
    <h2 class="{{ $compact ? 'text-lg' : 'text-xl' }} font-black text-prima-900">Create local job alerts</h2>
    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $compact ? 'Get matching jobs for your chosen country, role, and category.' : 'Choose the country, role, and category you care about. Prime only sends matching jobs for this alert.' }}</p>

    @if ($submitted)
        <div class="mt-4 rounded-lg bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">Check your email to confirm this alert. Matching browser push alerts will start after confirmation if this device is subscribed.</div>
    @else
        <form wire:submit="subscribe" class="{{ $compact ? 'mt-4' : 'mt-5' }} grid gap-3">
            <label class="grid min-w-0 gap-1 text-sm font-semibold text-slate-700">
                Email address
                <input wire:model="email" type="email" placeholder="you@example.com" class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
            </label>
            <label class="grid min-w-0 gap-1 text-sm font-semibold text-slate-700">
                Role or keyword
                <input wire:model="keyword" data-notification-filter="keyword" placeholder="Accounts Assistant, nurse, Laravel, driver" class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid min-w-0 gap-1 text-sm font-semibold text-slate-700">
                    Country
                    <select wire:model="country_code" data-notification-filter="country" class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                        <option value="">Any country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->iso2 }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid min-w-0 gap-1 text-sm font-semibold text-slate-700">
                    Category
                    <select wire:model="category_id" data-notification-filter="categoryId" class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="grid min-w-0 gap-1 text-sm font-semibold text-slate-700">
                Alert frequency
                <select wire:model="alert_frequency" class="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                    <option value="hourly">Hourly digest</option>
                    <option value="daily">Daily digest</option>
                    <option value="weekly">Weekly digest</option>
                </select>
            </label>
            @if ($browserPushEnabled)
                <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-3 text-sm font-semibold text-slate-700">
                    <button type="button" data-enable-notifications data-enabled-label="Browser notifications enabled" class="w-full rounded-lg bg-cyan-700 px-4 py-2 text-sm font-black text-white hover:bg-cyan-800">Enable matching browser notifications</button>
                    <span data-custom-push-status class="mt-2 block text-xs font-normal leading-5 text-slate-600" aria-live="polite">Your browser will ask once for permission. Your filters are stored securely on our own notification server.</span>
                </div>
            @else
                <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-semibold text-slate-500">
                    <input type="checkbox" class="mt-1 rounded border-slate-300" disabled>
                    <span>
                        Send matching browser push notifications to this device
                        <span class="block text-xs font-normal leading-5 text-slate-500">Browser push is being configured. Email alerts are available now.</span>
                    </span>
                </label>
            @endif
            <button class="rounded-lg bg-prima-900 px-4 py-2 text-sm font-black text-white hover:bg-prima-700">{{ $compact ? 'Subscribe' : 'Create alert' }}</button>
        </form>
    @endif

    @if (config('mobile.android.play_store_url') || config('mobile.ios.app_store_url'))
        <div class="mt-4 border-t border-slate-200 pt-4">
            <div class="min-w-0">
                <p class="text-sm font-black text-prima-900">Prefer app notifications?</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Prime Jobs Global is free on Android and iPhone.</p>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @if (config('mobile.android.play_store_url'))
                    <a href="{{ config('mobile.android.play_store_url') }}" class="rounded-lg border border-prima-200 px-3 py-2 text-xs font-black text-prima-700 hover:bg-prima-50">Get Android app</a>
                @endif
                @if (config('mobile.ios.app_store_url'))
                    <a href="{{ config('mobile.ios.app_store_url') }}" class="rounded-lg border border-prima-200 px-3 py-2 text-xs font-black text-prima-700 hover:bg-prima-50">Get iPhone app</a>
                @endif
            </div>
        </div>
    @endif
</div>
