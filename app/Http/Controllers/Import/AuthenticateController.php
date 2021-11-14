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
use App\Services\Enums\AuthenticationStatus;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\Session\Constants;
use App\Services\Spectre\AuthenticationValidator as SpectreValidator;
use Illuminate\Http\Request;


/**
 * Class AuthenticateController
 */
class AuthenticateController extends Controller
{
    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        $mainTitle = 'Auth';
        $pageTitle = 'Auth';
        $flow      = $request->cookie(Constants::FLOW_COOKIE);
        if ('csv' === $flow) {
            // redirect straight to upload
            return redirect(route('003-upload.index'));
        }

        if ('spectre' === $flow) {
            die('TODO');
            $subTitle  = 'Spectre';
            $validator = new SpectreValidator;
            $result    = $validator->validate();
            if ($result->equals(AuthenticationStatus::nodata())) {
                // show for to enter data. save as cookie.
                return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle'));
            }
            if ($result->equals(AuthenticationStatus::authenticated())) {
                return redirect(route('003-upload.index'));
            }
        }

        if ('nordigen' === $flow) {
            $subTitle  = 'Nordigen';
            $validator = new NordigenValidator;
            $result    = $validator->validate();
            if ($result->equals(AuthenticationStatus::nodata())) {

                $key = config('nordigen.key');
                $identifier = config('nordigen.identifier');

                // show for to enter data. save as cookie.
                return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle','key','identifier'));
            }
            if ($result->equals(AuthenticationStatus::authenticated())) {
                return redirect(route('003-upload.index'));
            }
        }
        throw new ImporterErrorException('Impossible flow exception.');
    }

    /**
     * @param Request $request
     */
    public function postIndex(Request $request)
    {
        // set cookies and redirect, validator will pick it up.
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if ('spectre' === $flow) {
            $appId  = $request->get('spectre_app_id');
            $secret = $request->get('spectre_secret');
            if ('' === $appId || '' === $secret) {
                // todo show error
                return redirect(route('002-authenticate.index'));
            }
            // store ID and key in session:
            session()->put(Constants::SESSION_SPECTRE_APP_ID, $appId);
            session()->put(Constants::SESSION_SPECTRE_SECRET, $secret);
            return redirect(route('002-authenticate.index'));
        }
        if ('nordigen' === $flow) {
            $key        = $request->get('nordigen_key');
            $identifier = $request->get('nordigen_id');
            if ('' === $key || '' === $identifier) {
                // todo show error
                return redirect(route('002-authenticate.index'));
            }
            // store ID and key in session:
            session()->put(Constants::SESSION_NORDIGEN_ID, $identifier);
            session()->put(Constants::SESSION_NORDIGEN_KEY, $key);

            return redirect(route('002-authenticate.index'));
        }

        throw new ImporterErrorException('Impossible flow exception.');
    }

}
