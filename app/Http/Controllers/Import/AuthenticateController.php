<?php

/*
 * AuthenticateController.php
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

namespace App\Http\Controllers\Import;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateControllerMiddleware;
use App\Services\Enums\AuthenticationStatus;
use App\Services\LunchFlow\AuthenticationValidator as LunchFlowValidator;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\Session\Constants;
use App\Services\Spectre\AuthenticationValidator as SpectreValidator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Session;

/**
 * Class AuthenticateController
 */
class AuthenticateController extends Controller
{
    private const string AUTH_ROUTE = '002-authenticate.index';

    public function __construct()
    {
        parent::__construct();
        Log::debug('Now in AuthenticateController, calling middleware.');
        $this->middleware(AuthenticateControllerMiddleware::class);
    }

    /**
     * @return Application|Factory|Redirector|RedirectResponse|View
     *
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
        Log::debug(sprintf('Now in AuthenticateController::index (/authenticate) with flow "%s"', $flow));

        // need a switch here to validate all possible flows.
        switch ($flow) {
            case 'spectre':
                $validator = new SpectreValidator();
                break;
            case 'nordigen':
                $validator = new NordigenValidator();
                break;
            case 'lunchflow':
                $validator = new LunchFlowValidator();
                break;
            default:
                Log::debug(sprintf('Throwing ImporterErrorException for flow "%s"', $flow ?? 'NULL'));
                throw new ImporterErrorException(sprintf('Impossible flow exception. Unexpected flow "%s" encountered.', $flow ?? 'NULL'));
        }

        $result    = $validator->validate();

        if (AuthenticationStatus::NODATA === $result) {
            // need to get and present the auth data in the system (yes it is always empty).
            $data = $validator->getData();

            return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle', 'data', 'error'));
        }

        if (AuthenticationStatus::AUTHENTICATED === $result) {
            Log::debug(sprintf('[a] Return redirect to %s', route('003-upload.index')));

            return redirect(route('003-upload.index'));
        }

        Log::debug(sprintf('Throwing ImporterErrorException for flow "%s"', $flow ?? 'NULL'));

        throw new ImporterErrorException(sprintf('Impossible flow exception. Unexpected flow "%s" encountered.', $flow ?? 'NULL'));
    }

    /**
     * @return Application|Redirector|RedirectResponse
     *
     * @throws ImporterErrorException
     */
    public function postIndex(Request $request)
    {
        // variables for page:
        $flow       = $request->cookie(Constants::FLOW_COOKIE);

        switch ($flow) {
            case 'spectre':
                $validator = new SpectreValidator();
                break;
            case 'nordigen':
                $validator = new NordigenValidator();
                break;
            case 'lunchflow':
                $validator = new LunchFlowValidator();
                break;
            default:
                Log::debug(sprintf('Throwing ImporterErrorException for flow "%s"', $flow ?? 'NULL'));
                throw new ImporterErrorException(sprintf('Impossible flow exception. Unexpected flow "%s" encountered.', $flow ?? 'NULL'));
        }
        $all        = $request->all();
        $submission = [];
        foreach ($all as $name => $value) {
            if (str_starts_with($name, $flow)) {
                $shortName              = str_replace(sprintf('%s_', $flow), '', $name);
                if ('' === (string) $value) {
                    return redirect(route(self::AUTH_ROUTE))->with(['error' => sprintf('The "%s"-field must be filled in.', $shortName)]);
                }
                $submission[$shortName] = (string) $value;
            }
        }
        $validator->setData($submission);

        return redirect(route(self::AUTH_ROUTE));
    }
}
