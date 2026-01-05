<?php

/*
 * api.php
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
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(
    [
        'prefix'    => '',
        'as'        => 'api.',
    ],
    static function (): void {
        // import flows
        Route::get('import-flows', 'ImportFlow\IndexController@index')->name('import-flows.index');
        Route::get('import-flows/validate/{flow}', 'ImportFlow\ValidationController@validateFlow')->name('import-flows.validate');

        // Firefly III connection validator:
        Route::get('firefly-iii/validate', 'Connection\IndexController@validateConnection')->name('firefly-iii.validate');

        // import jobs
        Route::get('import-jobs', 'ImportJob\IndexController@index')->name('import-jobs.index');
    }
);
