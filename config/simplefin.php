<?php

/*
 * simplefin.php
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
    /*
    |--------------------------------------------------------------------------
    | SimpleFIN Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SimpleFIN integration
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Demo Configuration
    |--------------------------------------------------------------------------
    */
    'demo_url'              => env('SIMPLEFIN_DEMO_URL', ''),
    'demo_token'            => env('SIMPLEFIN_DEMO_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    */
    'connection_timeout'    => env('SIMPLEFIN_CONNECTION_TIMEOUT', 30),
    'request_timeout'       => env('SIMPLEFIN_REQUEST_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Transaction Processing
    |--------------------------------------------------------------------------
    */
    'unique_column_options' => [
        'id' => 'Transaction ID',
        'account_id' => 'Account ID',
        'posted' => 'Posted Date',
        'amount' => 'Amount',
        'description' => 'Description',
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Mapping
    |--------------------------------------------------------------------------
    */
    'account_types'         => [
        'checking'  => 'asset',
        'savings'   => 'asset',
        'credit'    => 'debt',      // Credit cards are debt accounts
        'loan'      => 'loan',      // Loans use specific loan account type
        'mortgage'  => 'mortgage',  // Mortgages use specific mortgage account type
        'investment' => 'asset',
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    */
    'max_transactions'      => env('SIMPLEFIN_MAX_TRANSACTIONS', 10000),
    'default_date_range'    => env('SIMPLEFIN_DEFAULT_DATE_RANGE', 90), // days
    'enable_caching'        => env('SIMPLEFIN_ENABLE_CACHING', true),
    'cache_duration'        => env('SIMPLEFIN_CACHE_DURATION', 3600), // seconds

    /*
    |--------------------------------------------------------------------------
    | Expense Account Assignment
    |--------------------------------------------------------------------------
    */
    'smart_expense_matching' => env('SIMPLEFIN_SMART_EXPENSE_MATCHING', true),
    'expense_matching_threshold' => env('SIMPLEFIN_EXPENSE_MATCHING_THRESHOLD', 0.7), // Restored default for better clustering
    'auto_create_expense_accounts' => env('SIMPLEFIN_AUTO_CREATE_EXPENSE_ACCOUNTS', true),

    /*
    |--------------------------------------------------------------------------
    | Transaction Clustering (Clean Instances)
    |--------------------------------------------------------------------------
    */
    'enable_transaction_clustering' => env('SIMPLEFIN_ENABLE_TRANSACTION_CLUSTERING', true),
    'clustering_similarity_threshold' => env('SIMPLEFIN_CLUSTERING_SIMILARITY_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    */
    'retry_attempts'        => env('SIMPLEFIN_RETRY_ATTEMPTS', 3),
    'retry_delay'           => env('SIMPLEFIN_RETRY_DELAY', 1), // seconds
];