<?php
declare(strict_types=1);

/**
 * specifics.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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


return [
    // specifics:
    'ing_name'               => 'ING NL',
    'ing_descr'              => 'Create better descriptions in ING exports',
    'sns_name'               => 'SNS / Volksbank NL',
    'sns_descr'              => 'Trim quotes from SNS / Volksbank export files',
    'abn_name'               => 'ABN AMRO NL',
    'abn_descr'              => 'Fixes potential problems with ABN AMRO files',
    'rabo_name'              => 'Rabobank NL',
    'rabo_descr'             => 'Fixes potential problems with Rabobank files',
    'pres_name'              => 'President\'s Choice Financial CA',
    'pres_descr'             => 'Fixes potential problems with PC files',
    'belfius_name'           => 'Belfius BE',
    'belfius_descr'          => 'Fixes potential problems with Belfius files',
    'ingbelgium_name'        => 'ING BE',
    'ingbelgium_descr'       => 'Fixes potential problems with ING Belgium files',
    'hash_name'              => 'Append fingerprint',
    'hash_descr'             => 'Adds a column with transaction specific fingerprint unique per line',
];
