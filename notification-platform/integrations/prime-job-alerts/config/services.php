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

    'ai_translation' => [
        'enabled' => filter_var(env('AI_TRANSLATION_ENABLED', env('OPENAI_API_KEY') ? true : false), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('AI_TRANSLATION_PROVIDER', 'openai'),
        'openai_key' => env('OPENAI_API_KEY'),
        'model' => env('AI_TRANSLATION_MODEL', 'gpt-4o-mini'),
        'endpoint' => env('AI_TRANSLATION_ENDPOINT', 'https://api.openai.com/v1/responses'),
        'timeout' => (int) env('AI_TRANSLATION_TIMEOUT', 20),
    ],

    'ai_documents' => [
        'enabled' => filter_var(env('AI_DOCUMENTS_ENABLED', env('OPENAI_API_KEY') ? true : false), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('AI_DOCUMENTS_PROVIDER', 'openai'),
        'openai_key' => env('OPENAI_API_KEY'),
        'model' => env('AI_DOCUMENTS_MODEL', env('AI_TRANSLATION_MODEL', 'gpt-4o-mini')),
        'endpoint' => env('AI_DOCUMENTS_ENDPOINT', 'https://api.openai.com/v1/responses'),
        'timeout' => (int) env('AI_DOCUMENTS_TIMEOUT', 30),
    ],

    'notification_platform' => [
        'enabled' => filter_var(env('NOTIFICATION_PLATFORM_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('NOTIFICATION_PLATFORM_URL', 'https://api.alphilnetworks.com/notifications'),
        'site' => env('NOTIFICATION_PLATFORM_SITE', 'prime-job-alerts'),
        'key' => env('NOTIFICATION_PLATFORM_KEY'),
        'timeout' => (int) env('NOTIFICATION_PLATFORM_TIMEOUT', 15),
    ],

    'indexnow' => [
        'enabled' => filter_var(env('INDEXNOW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'timeout' => (int) env('INDEXNOW_TIMEOUT', 10),
    ],

    'google_indexing' => [
        'enabled' => filter_var(env('GOOGLE_INDEXING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'client_email' => env('GOOGLE_INDEXING_CLIENT_EMAIL'),
        'private_key' => env('GOOGLE_INDEXING_PRIVATE_KEY'),
        'private_key_path' => env('GOOGLE_INDEXING_PRIVATE_KEY_PATH'),
        'token_endpoint' => env('GOOGLE_INDEXING_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token'),
        'endpoint' => env('GOOGLE_INDEXING_ENDPOINT', 'https://indexing.googleapis.com/v3/urlNotifications:publish'),
        'timeout' => (int) env('GOOGLE_INDEXING_TIMEOUT', 10),
    ],

];
