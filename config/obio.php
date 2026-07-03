<?php

/*
 * obio.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

return [
    /*
    |--------------------------------------------------------------------------
    | open-banking.io Configuration
    |--------------------------------------------------------------------------
    |
    | open-banking.io is an affordable, certificate-free EU open banking API.
    | The user exports a "credentials.json" bundle from the app (API key + a
    | private key). The API returns zero-knowledge encrypted envelopes that are
    | decrypted locally with that private key -- the service never sees plaintext.
    |
    */
    // Base URL only (no trailing /api). The exported credentials bundle usually carries its
    // own "apiBaseUrl"; this is the fallback. Request paths are "api/accounts" etc.
    'api_url'               => envDefaultWhenEmpty(env('OBIO_API_URL'), 'https://open-banking.io'),

    // Optional: a whole credentials bundle supplied via the environment (JSON string).
    'credentials'           => env('OBIO_CREDENTIALS', ''),

    'unique_column_options' => [
        'external-id' => 'External identifier',
    ],

    // Safety cap on how many transactions are fetched per account per import run.
    'max_transactions'      => env('OBIO_MAX_TRANSACTIONS', 10000),

    // How many transactions to request per API page while paging through a statement.
    'page_size'             => env('OBIO_PAGE_SIZE', 500),
];
