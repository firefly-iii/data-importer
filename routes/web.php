<?php

/*
 * web.php
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

// index: no checks
Route::get('/', 'IndexController@index')->name('index');
Route::post('/', 'IndexController@postIndex')->name('index.post');
Route::get('/debug', 'DebugController@index')->name('debug');
Route::get('/health', 'HealthcheckController@check')->name('health');

// validate access token:
Route::get('/token', 'TokenController@index')->name('token.index');
Route::post('/token/client_id', 'TokenController@submitClientId')->name('token.submitClientId');
Route::get('/token/validate', 'TokenController@doValidate')->name('token.validate');
Route::get('/callback', 'TokenController@callback')->name('token.callback');

// validate services
Route::get('/validate/{provider}', 'ServiceController@validateProvider')->name('validate.provider');

// clear session
Route::get('/flush', 'IndexController@flush')->name('flush');

// step 2: Authenticate Nordigen / Spectre manually if necessary.
// check : must not be CSV flow. If so redirect to upload.
Route::get('/authenticate', 'Import\AuthenticateController@index')->name('002-authenticate.index');
Route::post('/authenticate', 'Import\AuthenticateController@postIndex')->name('002-authenticate.post');

// step 3: Upload CSV file + config file
// check : Must not already have uploaded files (HAS_UPLOAD). If so redirect to configuration.
Route::get('/upload', 'Import\UploadController@index')->name('003-upload.index');
Route::post('/upload', ['uses' => 'Import\UploadController@upload', 'as' => '003-upload.upload']);

// step 4: Configure import
// check : must not already have done configuration. If so, redirect to roles by default.
Route::get('/import/configure', ['uses' => 'Import\ConfigurationController@index', 'as' => '004-configure.index']);
Route::post('/import/configure', ['uses' => 'Import\ConfigurationController@postIndex', 'as' => '004-configure.post']);
Route::get('/import/configure/download', ['uses' => 'Import\DownloadController@download', 'as' => '004-configure.download']);
Route::get('/import/php_date', ['uses' => 'Import\ConfigurationController@phpDate', 'as' => '004-configure.php_date']);
Route::post('/import/check-duplicate', ['uses' => 'Import\DuplicateCheckController@checkDuplicate', 'as' => 'import.check-duplicate']);

// step 5: Set column roles (CSV or other file types)
// check : must be CSV and not config complete otherwise redirect to mapping.
Route::get('/import/roles', ['uses' => 'Import\File\RoleController@index', 'as' => '005-roles.index']);
Route::post('/import/roles', ['uses' => 'Import\File\RoleController@postIndex', 'as' => '005-roles.post']);

// step 6: mapping
// check: must be [CSV and roles complete] or [not csv and conversion complete] or go to conversion?
Route::get('/import/mapping', ['uses' => 'Import\MapController@index', 'as' => '006-mapping.index']);
Route::post('/import/mapping', ['uses' => 'Import\MapController@postIndex', 'as' => '006-mapping.post']);

// step 7: convert any import to JSON transactions
// check: config complete (see step 4) + mapping complete if CSV + roles complete  or not CSV.
Route::get('/import/convert', ['uses' => 'Import\ConversionController@index', 'as' => '007-convert.index']);
Route::any('/import/convert/start', ['uses' => 'Import\ConversionController@start', 'as' => '007-convert.start']);
Route::get('/import/convert/status', ['uses' => 'Import\ConversionController@status', 'as' => '007-convert.status']);

// step 8: submit JSON to Firefly III
Route::get('/import/submit', ['uses' => 'Import\SubmitController@index', 'as' => '008-submit.index']);
Route::any('/import/submit/start', ['uses' => 'Import\SubmitController@start', 'as' => '008-submit.start']);
Route::get('/import/submit/status', ['uses' => 'Import\SubmitController@status', 'as' => '008-submit.status']);

// step 9: Nordigen select a country + bank
Route::get('/import/selection', ['uses' => 'Import\Nordigen\SelectionController@index', 'as' => '009-selection.index']);
Route::post('/import/selection', ['uses' => 'Import\Nordigen\SelectionController@postIndex', 'as' => '009-selection.post']);

// step 10: Get redirected to + callback from Nordigen for permission:
Route::get('/import/link-nordigen/build', ['uses' => 'Import\Nordigen\LinkController@build', 'as' => '010-build-link.index']);
Route::get('/import/link-nordigen/callback', ['uses' => 'Import\Nordigen\LinkController@callback', 'as' => '010-build-link.callback']);

// step 11: list tokens (can be skipped)
Route::get('/import/spectre-connections', ['uses' => 'Import\Spectre\ConnectionController@index', 'as' => '011-connections.index']);
Route::post('/import/spectre-connections/submit', ['uses' => 'Import\Spectre\ConnectionController@post', 'as' => '011-connections.post']);
Route::get('/import/spectre-connections/callback', ['uses' => 'Import\Spectre\CallbackController@index', 'as' => '011-connections.callback']);



// routes to go back to other steps (also takes care of session vars)
Route::get('/back/start', 'NavController@toStart')->name('back.start');
Route::get('/back/upload', 'NavController@toUpload')->name('back.upload');
Route::get('/back/config', 'NavController@toConfig')->name('back.config');
Route::get('/back/mapping', 'NavController@toRoles')->name('back.mapping');
Route::get('/back/roles', 'NavController@toRoles')->name('back.roles');
Route::get('/back/conversion', 'NavController@toConversion')->name('back.conversion');

// import by POST
Route::post('/autoimport', 'AutoImportController@index')->name('autoimport');
Route::post('/autoupload', 'AutoUploadController@index')->name('autoupload');

//
// // start import thing.
// Route::get('/import/start', ['uses' => 'Import\StartController@index', 'as' => 'import.start']);
//
//
//
// // import config helper
//

//
// // download config:
//
