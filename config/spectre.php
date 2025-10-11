<?php

/*
 * spectre.php
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
    'customer_identifier'   => env('CUSTOMER_IDENTIFIER', 'default_ff3_customer'),
    'app_id'                => env('SPECTRE_APP_ID', ''),
    'secret'                => env('SPECTRE_SECRET', ''),
    'url'                   => 'https://www.saltedge.com/api/v6',
    'unique_column_options' => [
        'external-id' => 'External identifier',
    ],
];
