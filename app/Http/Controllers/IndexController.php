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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
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

    public function flush(): mixed
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        session()->forget([
            Constants::UPLOAD_DATA_FILE,
            Constants::UPLOAD_CONFIG_FILE,
            Constants::IMPORT_JOB_IDENTIFIER,
        ]);
        session()->flush();
        session()->regenerate(true);
        $cookies = [cookie(Constants::FLOW_COOKIE, '')];
        Artisan::call('cache:clear');
        // Artisan::call('config:clear'); // disable command to try and fix

        return redirect(route('index'))->withCookies($cookies);
    }

    public function index(Request $request): mixed
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // global methods to get these values, from cookies or configuration.
        // it's up to the manager to provide them.
        // if invalid values, redirect to token index.
        $validInfo         = SecretManager::hasValidSecrets();
        if (!$validInfo) {
            Log::debug('No valid secrets, redirect to token.index');

            return redirect(route('token.index'));
        }

        // display to user the method of authentication
        $clientId          = (string) config('importer.client_id');
        $url               = (string) config('importer.url');
        $accessTokenConfig = (string) config('importer.access_token');

        Log::debug('IndexController authentication detection', [
            'client_id'           => $clientId,
            'url'                 => $url,
            'access_token_config' => substr($accessTokenConfig, 0, 25).'...',
            'access_token_empty'  => '' === $accessTokenConfig,
        ]);

        $pat               = false;
        if ('' !== $accessTokenConfig) {
            $pat = true;
        }
        $clientIdWithURL   = false;
        if ('' !== $url && '' !== $clientId) {
            $clientIdWithURL = true;
        }
        $URLonly           = false;
        if ('' !== $url && '' === $clientId && '' === $accessTokenConfig) {
            $URLonly = true;
        }
        $flexible          = false;
        if ('' === $url && '' === $clientId) {
            $flexible = true;
        }

        Log::debug('IndexController authentication type flags', [
            'pat'             => $pat,
            'clientIdWithURL' => $clientIdWithURL,
            'URLonly'         => $URLonly,
            'flexible'        => $flexible,
        ]);

        $isDocker          = config('importer.docker.is_docker', false);
        $identifier        = substr(session()->getId(), 0, 10);
        $enabled           = config('importer.enabled_flows');

        return view('index', compact('pat', 'clientIdWithURL', 'URLonly', 'flexible', 'identifier', 'isDocker', 'enabled'));
    }

    public function postIndex(Request $request): mixed
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        // set cookie with flow:
        $flow = $request->get('flow');
        if (in_array($flow, config('importer.flows'), true)) {
            Log::debug(sprintf('%s is a valid flow, redirect to authenticate.', $flow));
            $cookies = [cookie(Constants::FLOW_COOKIE, $flow)];

            return redirect(route('002-authenticate.index'))->withCookies($cookies);
        }
        Log::debug(sprintf('"%s" is not a valid flow, redirect to index.', $flow));

        return redirect(route('index'));
    }
}
