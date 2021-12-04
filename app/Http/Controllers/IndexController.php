<?php
/*
 * IndexController.php
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

namespace App\Http\Controllers;

use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use Artisan;
use Illuminate\Http\Request;
use Log;

/**
 *
 * Class IndexController
 */
class IndexController extends Controller
{
    /**
     * IndexController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Index');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function postIndex(Request $request): mixed
    {
        // set cookie with flow:
        $flow = $request->get('flow');
        if (in_array($flow, config('importer.flows'), true)) {
            $cookies = [
                cookie(Constants::FLOW_COOKIE, $flow),
            ];
            return redirect(route('002-authenticate.index'))->withCookies($cookies);
        }
        return redirect(route('index'));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request): mixed
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        // global methods to get these values, from cookies or configuration.
        // it's up to the manager to provide them.
        // if invalid values, redirect to token index.
        $validInfo = SecretManager::hasValidSecrets();
        if (!$validInfo) {
            Log::debug('No valid secrets, redirect to token.index');
            return redirect(route('token.index'));
        }

        // display to user the method of authentication
        $pat = false;
        if ('' !== (string) config('importer.access_token')) {
            $pat = true;
        }
        $clientIdWithURL = false;
        if ('' !== (string) config('importer.url') && '' !== (string) config('importer.client_id')) {
            $clientIdWithURL = true;
        }
        $URLonly = false;
        if ('' !== (string) config('importer.url') && '' === (string) config('importer.client_id') && '' === (string) config('importer.access_token')
        ) {
            $URLonly = true;
        }
        $flexible = false;
        if ('' === (string) config('importer.url') && '' === (string) config('importer.client_id')) {
            $flexible = true;
        }

        return view('index', compact('pat', 'clientIdWithURL', 'URLonly', 'flexible'));
    }

    /**
     * @return mixed
     */
    public function reset(): mixed
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        session()->forget(['csv_file_path', 'config_file_path', 'import_job_id']);
        session()->flush();
        Artisan::call('cache:clear');

        $cookies = [
            SecretManager::saveAccessToken(''),
            SecretManager::saveBaseUrl(''),
            SecretManager::saveRefreshToken(''),
            cookie(Constants::FLOW_COOKIE, ''),
        ];

        return redirect(route('index'))->withCookies($cookies);
    }

    /**
     * @return mixed
     */
    public function flush(): mixed
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        session()->forget(['csv_file_path', 'config_file_path', 'import_job_id']);
        session()->flush();
        $cookies = [
            cookie(Constants::FLOW_COOKIE, ''),
        ];
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        return redirect(route('index'))->withCookies($cookies);
    }

}
