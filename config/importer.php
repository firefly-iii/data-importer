<?php
/*
 * importer.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

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
    'use_cache'         => env('USE_CACHE', false),
    'minimum_version'   => '5.6.4',
    'cache_api_calls'   => false,
    'ignored_files'     => ['.gitignore'],
    'tracker_site_id'   => env('TRACKER_SITE_ID', ''),
    'tracker_url'       => env('TRACKER_URL', ''),
    'vanity_url'        => envNonEmpty('VANITY_URL'),
    'connection'        => [
        'verify'  => env('VERIFY_TLS_SECURITY', true),
        'timeout' => 0.0 === (float) env('CONNECTION_TIMEOUT', 31.415) ? 31.415 : (float) env('CONNECTION_TIMEOUT', 31.415),
    ],
    'trusted_proxies'   => env('TRUSTED_PROXIES', ''),

];
