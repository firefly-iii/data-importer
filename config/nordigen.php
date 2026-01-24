<?php

/*
 * nordigen.php
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

return [
    'id'                    => env('NORDIGEN_ID', ''),
    'key'                   => env('NORDIGEN_KEY', ''),
    'url'                   => 'https://bankaccountdata.gocardless.com',
    'use_sandbox'           => env('NORDIGEN_SANDBOX', false),
    'respect_rate_limit'    => true,
    'exit_for_rate_limit'   => 'exit' === env('RESPOND_TO_GOCARDLESS_LIMIT', 'wait'),
    'get_account_details'   => env('GOCARDLESS_GET_ACCOUNT_DETAILS', false),
    'get_balance_details'   => env('GOCARDLESS_GET_BALANCE_DETAILS', false),
    'unique_column_options' => [
        'external-id'            => 'External identifier',
        'additional-information' => 'Additional information',
    ],
    'countries'             => require __DIR__ . '/shared/countries.php',
];
