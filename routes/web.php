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

// new routes that use less session data and redirects.
Route::get('/authenticate-flow/{flow?}', 'Import\AuthenticateController@index')->name('authenticate-flow.index');
Route::post('/authenticate-flow/{flow}', 'Import\AuthenticateController@postIndex')->name('authenticate-flow.post');

Route::get('/new-import/{flow?}', 'Import\UploadController@index')->name('new-import.index');
Route::post('/new-import/{flow}', 'Import\UploadController@upload')->name('new-import.post');

Route::get('/configure-import/{identifier}', 'Import\ConfigurationController@index')->name('configure-import.index');
Route::post('/configure-import/{identifier}', ['uses' => 'Import\ConfigurationController@postIndex', 'as' => 'configure-import.post']);
Route::get('/download-import-configuration/{identifier}', ['uses' => 'Import\DownloadController@download', 'as' => 'configure-import.download']);
Route::post('/check-duplicate-account/{identifier}', ['uses' => 'Import\DuplicateCheckController@checkDuplicate', 'as' => 'configure-import.check-duplicate']);



Route::get('/configure-roles/{identifier}', ['uses' => 'Import\File\RoleController@index', 'as' => 'configure-roles.index']);
Route::post('/configure-roles/{identifier}', ['uses' => 'Import\File\RoleController@postIndex', 'as' => 'configure-roles.post']);

Route::get('/data-mapping/{identifier}', ['uses' => 'Import\MapController@index', 'as' => 'data-mapping.index']);
Route::post('/data-mapping/{identifier}', ['uses' => 'Import\MapController@postIndex', 'as' => 'data-mapping.post']);

Route::get('/data-conversion/{identifier}', ['uses' => 'Import\ConversionController@index', 'as' => 'data-conversion.index']);
Route::any('/data-conversion/{identifier}/start', ['uses' => 'Import\ConversionController@start', 'as' => 'data-conversion.start']);
Route::get('/data-conversion/{identifier}/status', ['uses' => 'Import\ConversionController@status', 'as' => 'data-conversion.status']);

Route::get('/submit-data/{identifier}', ['uses' => 'Import\SubmitController@index', 'as' => 'submit-data.index']);
Route::any('/submit-data/{identifier}/start', ['uses' => 'Import\SubmitController@start', 'as' => 'submit-data.start']);
Route::get('/submit-data/{identifier}/status', ['uses' => 'Import\SubmitController@status', 'as' => 'submit-data.status']);


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
Route::get('/authenticate/{flow?}', 'Import\AuthenticateController@index')->name('002-authenticate.index');
Route::post('/authenticate', 'Import\AuthenticateController@postIndex')->name('002-authenticate.post');

// step 3: Upload CSV file + config file
// check : Must not already have uploaded files (HAS_UPLOAD). If so redirect to configuration.
//Route::get('/upload/{flow?}', 'Import\UploadController@index')->name('003-upload.index');
//Route::post('/upload', ['uses' => 'Import\UploadController@upload', 'as' => '003-upload.upload']);

// step 4: Configure import
// check : must not already have done configuration. If so, redirect to roles by default.
//Route::get('/import/configure', ['uses' => 'Import\ConfigurationController@index', 'as' => '004-configure.index']);
//Route::post('/import/configure', ['uses' => 'Import\ConfigurationController@postIndex', 'as' => '004-configure.post']);

Route::get('/import/php_date', ['uses' => 'Import\ConfigurationController@phpDate', 'as' => '004-configure.php_date']);

// step 5: Set column roles (CSV or other file types)
// check : must be CSV and not config complete otherwise redirect to mapping.
//Route::get('/import/roles', ['uses' => 'Import\File\RoleController@index', 'as' => '005-roles.index']);
//Route::post('/import/roles', ['uses' => 'Import\File\RoleController@postIndex', 'as' => '005-roles.post']);

// step 6: mapping
// check: must be [CSV and roles complete] or [not csv and conversion complete] or go to conversion?
//Route::get('/import/mapping', ['uses' => 'Import\MapController@index', 'as' => '006-mapping.index']);
//Route::post('/import/mapping', ['uses' => 'Import\MapController@postIndex', 'as' => '006-mapping.post']);

// step 7: convert any import to JSON transactions
// check: config complete (see step 4) + mapping complete if CSV + roles complete  or not CSV.
//Route::get('/import/convert', ['uses' => 'Import\ConversionController@index', 'as' => '007-convert.index']);
//Route::any('/import/convert/start', ['uses' => 'Import\ConversionController@start', 'as' => '007-convert.start']);
//Route::get('/import/convert/status', ['uses' => 'Import\ConversionController@status', 'as' => '007-convert.status']);

// step 8: submit JSON to Firefly III
//Route::get('/import/submit', ['uses' => 'Import\SubmitController@index', 'as' => '008-submit.index']);
//Route::any('/import/submit/start', ['uses' => 'Import\SubmitController@start', 'as' => '008-submit.start']);
//Route::get('/import/submit/status', ['uses' => 'Import\SubmitController@status', 'as' => '008-submit.status']);

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
//Route::get('/back/start', 'NavController@toStart')->name('back.start');
//Route::get('/back/upload', 'NavController@toUpload')->name('back.upload');
//Route::get('/back/config', 'NavController@toConfig')->name('back.config');
//Route::get('/back/mapping', 'NavController@toRoles')->name('back.mapping');
//Route::get('/back/roles', 'NavController@toRoles')->name('back.roles');
//Route::get('/back/conversion', 'NavController@toConversion')->name('back.conversion');


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
