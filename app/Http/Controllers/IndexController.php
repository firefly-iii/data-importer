<?php
/**
 * IndexController.php
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

namespace App\Http\Controllers;

use App\Services\Session\Constants;
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
     */
    public function postIndex(Request $request)
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
        // check for access token cookie. if not, redirect to flow to get it.
        $accessToken  = (string) $request->cookie('access_token');
        $refreshToken = (string) $request->cookie('refresh_token');
        $baseURL      = (string) $request->cookie('base_url');
        $vanityURL    = (string) $request->cookie('vanity_url');

        Log::debug(sprintf('Base URL   : "%s"', $baseURL));
        Log::debug(sprintf('Vanity URL : "%s"', $vanityURL));

        if ('' === $accessToken && '' === $refreshToken && '' === $baseURL) {
            Log::debug('No access token cookie, redirect to token.index');
            return redirect(route('token.index'));
        }
        Log::debug('Has access token cookie.');

        // display to user the method of authentication
        $pat = false;
        if ('' !== (string) env('FIREFLY_III_ACCESS_TOKEN')) {
            $pat = true;
        }
        $clientIdWithURL = false;
        if ('' !== (string) env('FIREFLY_III_URL') && '' !== (string) env('FIREFLY_III_CLIENT_ID')) {
            $clientIdWithURL = true;
        }
        $URLonly = false;
        if ('' !== (string) env('FIREFLY_III_URL') && '' === (string) env('FIREFLY_III_CLIENT_ID') && '' === (string) env('FIREFLY_III_ACCESS_TOKEN')
        ) {
            $URLonly = true;
        }
        $flexible = false;
        if ('' === (string) env('FIREFLY_III_URL') && '' === (string) env('FIREFLY_III_CLIENT_ID')) {
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
            cookie('access_token', ''),
            cookie('base_url', ''),
            cookie('refresh_token', ''),
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
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        return redirect(route('index'));
    }

}
