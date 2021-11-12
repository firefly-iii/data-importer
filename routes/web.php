<?php
/*
 * web.php
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

Route::get('/', 'IndexController@index')->name('index');
Route::post('/', 'IndexController@postIndex')->name('index.post');

// validate access token:
Route::get('/token', 'TokenController@index')->name('token.index');
Route::post('/token/client_id', 'TokenController@submitClientId')->name('token.submitClientId');
Route::get('/token/validate', 'TokenController@doValidate')->name('token.validate');
Route::get('/callback', 'TokenController@callback')->name('token.callback');

// validate services
Route::get('/validate/spectre', 'ServiceController@validateSpectre')->name('validate.spectre');
Route::get('/validate/nordigen', 'ServiceController@validateNordigen')->name('validate.nordigen');

// clear session
Route::get('/flush','IndexController@flush')->name('flush');
Route::get('/reset','IndexController@reset')->name('reset');

// step 2: Authenticate Nordigen / Spectre manually if necessary.
Route::get('/authenticate', 'Import\AuthenticateController@index')->name('002-authenticate.index');

// step 3: Upload CSV file + config file
Route::get('/upload', 'Import\UploadController@index')->name('003-upload.index');

//
//// routes to go back to other steps (also takes care of session vars)
//Route::get('/back/start', 'NavController@toStart')->name('back.start');
//Route::get('/back/upload', 'NavController@toUpload')->name('back.upload');
//Route::get('/back/config', 'NavController@toConfig')->name('back.config');
//Route::get('/back/roles', 'NavController@toRoles')->name('back.roles');
//Route::get('/back/mapping', 'NavController@toRoles')->name('back.mapping');
//
//// import by POST
//Route::post('/autoimport', 'AutoImportController@index')->name('autoimport');
//Route::post('/autoupload', 'AutoUploadController@index')->name('autoupload');
//

//
//// start import thing.
//Route::get('/import/start', ['uses' => 'Import\StartController@index', 'as' => 'import.start']);
//Route::post('/import/upload', ['uses' => 'Import\UploadController@upload', 'as' => 'import.upload']);
//
//Route::get('/import/configure', ['uses' => 'Import\ConfigurationController@index', 'as' => 'import.configure.index']);
//Route::post('/import/configure', ['uses' => 'Import\ConfigurationController@postIndex', 'as' => 'import.configure.post']);
//
//// import config helper
//Route::get('/import/php_date', ['uses' => 'Import\ConfigurationController@phpDate', 'as' => 'import.configure.php_date']);
//
//// roles
//Route::get('/import/roles', ['uses' => 'Import\RoleController@index', 'as' => 'import.roles.index']);
//Route::post('/import/roles', ['uses' => 'Import\RoleController@postIndex', 'as' => 'import.roles.post']);
//
//// download config:
//Route::get('/configuration/download', ['uses' => 'Import\DownloadController@download', 'as' => 'import.job.configuration.download']);
//
//// mapping
//Route::get('/import/mapping', ['uses' => 'Import\MapController@index', 'as' => 'import.mapping.index']);
//Route::post('/import/mapping', ['uses' => 'Import\MapController@postIndex', 'as' => 'import.mapping.post']);
//
//// run import
//Route::get('/import/run', ['uses' => 'Import\RunController@index', 'as' => 'import.run.index']);
//
//// start import routine.
//Route::any('/import/job/start', ['uses' => 'Import\RunController@start', 'as' => 'import.job.start']);
//Route::get('/import/job/status', ['uses' => 'Import\RunController@status', 'as' => 'import.job.status']);
