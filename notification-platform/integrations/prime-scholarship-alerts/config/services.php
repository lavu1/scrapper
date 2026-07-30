<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'notification_platform' => [
        'enabled' => filter_var(env('NOTIFICATION_PLATFORM_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('NOTIFICATION_PLATFORM_URL', 'https://api.alphilnetworks.com/notifications'),
        'site' => env('NOTIFICATION_PLATFORM_SITE', 'prime-scholarship-alerts'),
        'key' => env('NOTIFICATION_PLATFORM_KEY'),
        'timeout' => (int) env('NOTIFICATION_PLATFORM_TIMEOUT', 15),
    ],

    'indexnow' => [
        'enabled' => (bool) env('INDEXNOW_ENABLED', false),
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    ],

];
