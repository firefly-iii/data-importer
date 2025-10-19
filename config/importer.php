<?php

/*
 * importer.php
 * Copyright (c) 2025 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
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


/*
 * The preferred order for ALL workflows is as follows:
 *
 * FILE workflow
 * - 2: Upload
 * - 3: Configuration
 * - 4: Role selection
 * - 5: Map data
 * - 6: Convert data
 * - 7: Submit data
 *
 *  NORDIGEN / GOCARDLESS workflow
 *  - 1: Authentication
 *  - 2: Upload
 *  - 8: Select country and bank.
 *  - 3: Configuration
 *  - 4: Role selection
 *  - 5: Map data
 *  - 6: Convert data
 *  - 7: Submit data
 */

return [
    'version'                       => '1.9.0',
    'build_time'                    => 1760890845,
    'flows'                         => ['nordigen', 'spectre', 'file', 'simplefin', 'lunchflow', 'obg', 'eb', 'teller', 'fints', 'basiq'],
    'fake_data'                     => env('FAKE_DATA', false),
    'enabled_flows'                 => [
        'nordigen'  => true,
        'spectre'   => true,
        'file'      => true,
        'simplefin' => true,
        'lunchflow' => true,
        'obg'       => false,
        'eb'        => false,
        'teller'    => false,
        'fints'     => false,
        'basiq'     => false,
    ],
    'flow_titles'                   => [
        'file'      => 'File',
        'nordigen'  => 'GoCardless',
        'spectre'   => 'Spectre',
        'simplefin' => 'SimpleFIN',
        'lunchflow' => 'Lunch Flow',
        'obg'       => 'Open Banking Gateway',
        'eb'        => 'Enable Banking',
        'teller'    => 'Teller.io',
        'fints'     => 'FinTS/HBCI',
        'basiq'     => 'Basiq.io',
    ],
    'simplefin'                     => [
        'demo_url'   => env('SIMPLEFIN_DEMO_URL', 'https://demo:demo@beta-bridge.simplefin.org/simplefin'),
        'demo_token' => env('SIMPLEFIN_DEMO_TOKEN', 'demo'), // This token is used as the password in the demo_url
        'bridge_url' => env('SIMPLEFIN_BRIDGE_URL'),
        'timeout'    => (int)env('SIMPLEFIN_TIMEOUT', 30),
    ],

    // to determine which steps are possible for each import flow, the following properties have been defined.
    'can_define_roles'              => [
        // do you need to set roles (source, destination) for this flow?
        'file'      => true,
        'nordigen'  => false,
        'spectre'   => false,
        'simplefin' => false,
        'lunchflow' => false,
        'obg'       => false,
        'eb'        => false,
        'teller'    => false,
        'fints'     => false,
        'basiq'     => false,
    ],

    // docker build info.
    'docker'                        => [
        'is_docker'  => env('IS_DOCKER', false),
        'base_build' => env('BASE_IMAGE_BUILD', '(unknown)'),
    ],

    'fallback_in_dir'               => env('FALLBACK_IN_DIR', false),
    'fallback_configuration'        => '_fallback.json',
    'import_dir_allowlist'          => explode(',', (string)env('IMPORT_DIR_ALLOWLIST', '')),
    'auto_import_secret'            => env('AUTO_IMPORT_SECRET', ''),
    'can_post_autoimport'           => env('CAN_POST_AUTOIMPORT', false),
    'can_post_files'                => env('CAN_POST_FILES', false),
    'access_token'                  => env('FIREFLY_III_ACCESS_TOKEN'),
    'url'                           => env('FIREFLY_III_URL'),
    'client_id'                     => env('FIREFLY_III_CLIENT_ID'),
    'upload_path'                   => storage_path('uploads'),
    'log_return_json'               => env('LOG_RETURN_JSON', false),
    'expect_secure_url'             => env('EXPECT_SECURE_URL', false),
    'is_external'                   => env('IS_EXTERNAL', false),
    'ignore_duplicate_errors'       => env('IGNORE_DUPLICATE_ERRORS', false),
    'ignore_not_found_transactions' => env('IGNORE_NOT_FOUND_TRANSACTIONS', false),
    'namespace'                     => 'c40dcba2-411d-11ec-973a-0242ac130003',
    'use_cache'                     => env('USE_CACHE', false),
    'minimum_version'               => '6.4.2',
    'cache_api_calls'               => false,
    'ignored_files'                 => ['.gitignore'],
    'tracker_site_id'               => env('TRACKER_SITE_ID', ''),
    'tracker_url'                   => env('TRACKER_URL', ''),
    'vanity_url'                    => envNonEmpty('VANITY_URL'),
    'connection'                    => [
        'verify'  => env('VERIFY_TLS_SECURITY', true),
        'timeout' => 0.0 === (float)env('CONNECTION_TIMEOUT', 31.415) ? 31.415 : (float)env('CONNECTION_TIMEOUT', 31.415),
    ],
    'trusted_proxies'               => env('TRUSTED_PROXIES', ''),
    'encoding'                      => [
        'Quoted-Printable',
        '7bit',
        '8bit',
        'UCS-4',
        'UCS-4BE',
        'UCS-4LE',
        'UCS-2',
        'UCS-2BE',
        'UCS-2LE',
        'UTF-32',
        'UTF-32BE',
        'UTF-32LE',
        'UTF-16',
        'UTF-16BE',
        'UTF-16LE',
        'UTF-8',
        'UTF-7',
        'UTF7-IMAP',
        'ASCII',
        'Windows-1252',
        'Windows-1254',
        'ISO-8859-1',
        'ISO-8859-2',
        'ISO-8859-3',
        'ISO-8859-4',
        'ISO-8859-5',
        'ISO-8859-6',
        'ISO-8859-7',
        'ISO-8859-8',
        'ISO-8859-9',
        'ISO-8859-10',
        'ISO-8859-13',
        'ISO-8859-14',
        'ISO-8859-15',
        'ISO-8859-16',
        'Windows-1251',
    ],
    // some random lines for the data importer to use.
    'line_a'                        => 'Everything precious is fragile',
    'line_b'                        => 'Forgive yourself for not being at peace.',
    'line_c'                        => 'Doesnt look like anything to me.',
    'line_d'                        => 'Don’t feel so sorry for yourself. Make do.',
    'line_e'                        => 'All the decisive blows are struck left-handed.',

    'http_codes'                    => [
        0   => 'Unknown Error',
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Checkpoint',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        429 => 'Too Many Requests',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
    ],
];
