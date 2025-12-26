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
use App\Services\Enums\AuthenticationStatus;
use App\Services\LunchFlow\AuthenticationValidator as LunchFlowValidator;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
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
    private const string AUTH_ROUTE = 'authenticate-flow.index';

    public function __construct()
    {
        parent::__construct();
        Log::debug('Now in AuthenticateController, calling middleware.');
    }

    /**
     * @return Application|Factory|Redirector|RedirectResponse|View
     *
     * @throws ImporterErrorException
     */
    public function index(Request $request, string $flow)
    {
        // variables for page:
        $mainTitle = 'Authentication';
        $pageTitle = 'Authentication';
        $flow ??= 'file';
        $subTitle  = ucfirst($flow);
        $error     = Session::get('error');
        Log::debug(sprintf('Now in AuthenticateController::index (/authenticate) with flow "%s"', $flow));

        // if the flow is actually validated, or not validateable (like the "file" flow),
        // give a friendly error page.
        $validator = $this->getValidator($flow);
        if (null === $validator) {
            return view('import.002-authenticate.already-authenticated')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle'));
        }

        $result    = $validator->validate();

        if (AuthenticationStatus::NODATA === $result) {
            // need to get and present the auth data in the system (yes it is always empty).
            $data = $validator->getData();

            return view('import.002-authenticate.index')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle', 'data', 'error'));
        }

        if (AuthenticationStatus::AUTHENTICATED === $result) {
            Log::debug('[a] Return redirect to already authenticated view');

            return view('import.002-authenticate.already-authenticated')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle'));
        }

        Log::debug(sprintf('Throwing ImporterErrorException for flow "%s"', $flow ?? 'NULL'));

        throw new ImporterErrorException(sprintf('[b] Impossible flow exception. Unexpected flow "%s" encountered.', $flow ?? 'NULL'));
    }

    private function getValidator(string $flow): ?AuthenticationValidatorInterface
    {
        // need a switch here to validate all possible flows.
        switch ($flow) {
            case 'spectre':
                return new SpectreValidator();

            case 'nordigen':
                return new NordigenValidator();

            case 'lunchflow':
                return new LunchFlowValidator();
        }

        return null;
    }

    public function postIndex(Request $request, string $flow)
    {
        $mainTitle  = 'Authentication';
        $pageTitle  = 'Authentication';
        $subTitle   = ucfirst($flow);
        $validator  = $this->getValidator($flow);
        if (null === $validator) {
            return view('import.002-authenticate.already-authenticated')->with(compact('mainTitle', 'flow', 'subTitle', 'pageTitle'));
        }

        $all        = $request->all();
        $submission = [];
        foreach ($all as $name => $value) {
            if (str_starts_with((string)$name, $flow)) {
                $shortName              = str_replace(sprintf('%s_', $flow), '', $name);
                if ('' === (string)$value) {
                    return redirect(route(self::AUTH_ROUTE, [$flow]))->with(['error' => sprintf('The "%s"-field must be filled in.', $shortName)]);
                }
                $submission[$shortName] = (string)$value;
            }
        }
        $validator->setData($submission);

        return redirect(route('new-import.index', [$flow]));
    }
}
