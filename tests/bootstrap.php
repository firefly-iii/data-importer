<?php
/**
 * bootstrap.php
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

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require_once __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Bootstrap The Test Environment
|--------------------------------------------------------------------------
|
| You may specify console commands that execute once before your test is
| run. You are free to add your own additional commands or logic into
| this file as needed in order to help your test suite run quicker.
|
*/

$commands = [
    'config:cache',
    'event:cache',
];

$app = require __DIR__ . '/../bootstrap/app.php';

$console = tap($app->make(Kernel::class))->bootstrap();

foreach ($commands as $command) {
    $console->call($command);
}
