<?php
/*
 * AuthenticateController.php
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

namespace App\Http\Controllers\Import;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateControllerMiddleware;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Nordigen\Authentication\SecretManager as NordigenSecretManager;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\Session\Constants;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\AuthenticationValidator as SpectreValidator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Session;

/**
 * Class AuthenticateController
 */
class AuthenticateController extends Controller
{
    private const AUTH_ROUTE = '002-authenticate.index';

    public function __construct()
    {
        parent::__construct();
        app('log')->debug('Now in AuthenticateController, calling middleware.');
        $this->middleware(AuthenticateControllerMiddleware::class);
    }

    /**
     * @param  Request  $request
     *
     * @return Application|Factory|View|RedirectResponse|Redirector
     * @throws ImporterErrorException
     */
    public function index(Request $request)
    {
        // variables for page:
        $mainTitle = 'Authentication';
        $pageTitle = 'Authentication';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);
        $subTitle  = ucfirst($flow);
        $error     = Session::get('error');

        if ('spectre' === $flow) {
            $validator = new SpectreValidator();
            $result    = $validator->validate();
            if ($result->equals(AuthenticationStatus::nodata())) {
                // show for to enter data. save as cookie.
                return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle', 'error'));
            }
            if ($result->equals(AuthenticationStatus::authenticated())) {
                return redirect(route('003-upload.index'));
            }
        }

        if ('nordigen' === $flow) {
            $validator = new NordigenValidator();
            $result    = $validator->validate();
            if ($result->equals(AuthenticationStatus::nodata())) {
                $key        = NordigenSecretManager::getKey();
                $identifier = NordigenSecretManager::getId();

                // show for to enter data. save as cookie.
                return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle', 'key', 'identifier'));
            }
            if ($result->equals(AuthenticationStatus::authenticated())) {
                return redirect(route('003-upload.index'));
            }
        }
        throw new ImporterErrorException('Impossible flow exception.');
    }

    /**
     * @param  Request  $request
     *
     * @return Application|RedirectResponse|Redirector
     * @throws ImporterErrorException
     */
    public function postIndex(Request $request)
    {
        // variables for page:
        $mainTitle = 'Authentication';
        $pageTitle = 'Authentication';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);
        $subTitle  = ucfirst($flow);

        // set cookies and redirect, validator will pick it up.
        if ('spectre' === $flow) {
            $appId  = (string)$request->get('spectre_app_id');
            $secret = (string)$request->get('spectre_secret');
            if ('' === $appId || '' === $secret) {
                return redirect(route(self::AUTH_ROUTE))->with(['error' => 'Both fields must be filled in.']);
            }
            // give to secret manager to store:
            SpectreSecretManager::saveAppId($appId);
            SpectreSecretManager::saveSecret($secret);

            return redirect(route(self::AUTH_ROUTE));
        }
        if ('nordigen' === $flow) {
            $key        = $request->get('nordigen_key');
            $identifier = $request->get('nordigen_id');
            if ('' === $key || '' === $identifier) {
                return redirect(route(self::AUTH_ROUTE))->with(['error' => 'Both fields must be filled in.']);
            }
            // store ID and key in session:
            $cookies = [
                NordigenSecretManager::saveId($identifier),
                NordigenSecretManager::saveKey($key),
            ];

            return redirect(route(self::AUTH_ROUTE))->withCookies($cookies);
        }

        throw new ImporterErrorException('Impossible flow exception.');
    }
}
