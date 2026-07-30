@if ((bool) config('services.notification_platform.enabled'))
    <script
        src="{{ rtrim(config('services.notification_platform.url'), '/') }}/assets/client.js"
        data-site="{{ config('services.notification_platform.site') }}"
        data-api="{{ rtrim(config('services.notification_platform.url'), '/') }}"
        defer
    ></script>
@endif
