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
    'version'                 => '0.6.0',
    'flows'                   => ['nordigen', 'spectre', 'csv'],
    'flow_titles'             => [
        'csv'      => 'CSV',
        'nordigen' => 'Nordigen',
        'spectre'  => 'Spectre',
    ],
    'import_dir_whitelist'    => explode(',', env('IMPORT_DIR_WHITELIST', '')),
    'auto_import_secret'      => env('AUTO_IMPORT_SECRET', ''),
    'can_post_autoimport'     => env('CAN_POST_AUTOIMPORT', false),
    'can_post_files'          => env('CAN_POST_FILES', false),
    'access_token'            => env('FIREFLY_III_ACCESS_TOKEN'),
    'url'                     => env('FIREFLY_III_URL'),
    'client_id'               => env('FIREFLY_III_CLIENT_ID'),
    'upload_path'             => storage_path('uploads'),
    'expect_secure_url'       => env('EXPECT_SECURE_URL', false),
    'is_external'             => env('IS_EXTERNAL', false),
    'ignore_duplicate_errors' => env('IGNORE_DUPLICATE_ERRORS', false),
    'namespace'               => 'c40dcba2-411d-11ec-973a-0242ac130003',
    'use_cache'               => env('USE_CACHE', false),
    'minimum_version'         => '5.6.8',
    'cache_api_calls'         => false,
    'ignored_files'           => ['.gitignore'],
    'tracker_site_id'         => env('TRACKER_SITE_ID', ''),
    'tracker_url'             => env('TRACKER_URL', ''),
    'vanity_url'              => envNonEmpty('VANITY_URL'),
    'connection'              => [
        'verify'  => env('VERIFY_TLS_SECURITY', true),
        'timeout' => 0.0 === (float) env('CONNECTION_TIMEOUT', 31.415) ? 31.415 : (float) env('CONNECTION_TIMEOUT', 31.415),
    ],
    'trusted_proxies'         => env('TRUSTED_PROXIES', ''),
    'encoding'                => ['Quoted-Printable', '7bit', '8bit', 'UCS-4', 'UCS-4BE', 'UCS-4LE', 'UCS-2', 'UCS-2BE', 'UCS-2LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-8', 'UTF-7', 'UTF7-IMAP', 'ASCII', 'Windows-1252', 'Windows-1254', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'Windows-1251',],

];
