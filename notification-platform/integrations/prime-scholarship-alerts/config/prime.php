<?php

return [
    'api_rate_limit_per_minute' => (int) env('PRIME_API_RATE_LIMIT_PER_MINUTE', 60),
    'trusted_country_header_proxies' => array_filter(explode(',', (string) env('PRIME_TRUSTED_COUNTRY_HEADER_PROXIES', ''))),
    'geoip_database_path' => env('PRIME_GEOIP_DATABASE_PATH'),
    'cv_upload_disk' => env('PRIME_CV_UPLOAD_DISK', 'private'),
    'cv_upload_max_kb' => (int) env('PRIME_CV_UPLOAD_MAX_KB', 5120),
    'public_url' => env('APP_URL', 'http://localhost:8000'),
    'mobile_apps' => [
        'ios' => [
            'app_id' => (string) env('PRIME_IOS_APP_ID', '6788024265'),
            'url' => env('PRIME_IOS_APP_URL', 'https://apps.apple.com/us/app/prime-scholarship-alerts/id6788024265'),
        ],
        'android' => [
            'url' => env('PRIME_ANDROID_APP_URL', 'https://play.google.com/store/apps/details?id=com.alphil.networks.gradscholar'),
        ],
    ],
];
