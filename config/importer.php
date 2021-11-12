<?php

declare(strict_types=1);

return [
    'version'           => '0.1.0',
    'flows'             => ['nordigen', 'spectre', 'csv'],
    'access_token'      => env('FIREFLY_III_ACCESS_TOKEN'),
    'url'               => env('FIREFLY_III_URL'),
    'client_id'         => env('FIREFLY_III_CLIENT_ID'),
    'upload_path'       => storage_path('uploads'),
    'expect_secure_url' => env('EXPECT_SECURE_URL', false),
    'is_external'       => env('IS_EXTERNAL', false),
    'minimum_version'   => '5.6.0',
    'cache_api_calls'   => false,
    'tracker_site_id'   => env('TRACKER_SITE_ID', ''),
    'tracker_url'       => env('TRACKER_URL', ''),
    'vanity_url'        => envNonEmpty('VANITY_URL'),
    'connection'        => [
        'verify'  => env('VERIFY_TLS_SECURITY', true),
        'timeout' => 0.0 === (float) env('CONNECTION_TIMEOUT', 31.415) ? 31.415 : (float) env('CONNECTION_TIMEOUT', 31.415),
    ],
    'trusted_proxies'   => env('TRUSTED_PROXIES', ''),

];
