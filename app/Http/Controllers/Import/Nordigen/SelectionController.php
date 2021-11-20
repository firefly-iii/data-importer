<?php
declare(strict_types=1);
/*
 * SelectionController.php
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

namespace App\Http\Controllers\Import\Nordigen;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SelectionControllerMiddleware;
use App\Http\Request\SelectionRequest;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Nordigen\Request\ListBanksRequest;
use App\Services\Nordigen\Response\ErrorResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;
use Log;

/**
 * Class SelectionController
 */
class SelectionController extends Controller
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(SelectionControllerMiddleware::class);
    }

    /**
     * Step 9, select a country + bank.
     * @return Factory|View
     * @throws ImporterErrorException
     */
    public function index()
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $countries = config('nordigen.countries');
        $mainTitle = 'Selection';
        $subTitle  = 'Select your country and the bank you wish to use.';

        // get banks and countries
        $accessToken = TokenManager::getAccessToken();
        $url         = config('nordigen.url');

        $request = new ListBanksRequest($url, $accessToken);
        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }

        if ($response instanceof ErrorResponse) {
            throw new ImporterErrorException((string) $response->message);
        }
        return view('import.009-selection.index', compact('mainTitle', 'subTitle', 'response', 'countries'));
    }

    /**
     * @param Request $request
     */
    public function postIndex(SelectionRequest $request)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        // create a new config thing
        $configuration = Configuration::fromArray([]);
        if (session()->has(Constants::CONFIGURATION)) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        }
        $values = $request->getAll();

        // overrule with sandbox?
        if (config('nordigen.use_sandbox')) {
            $values['bank'] = 'SANDBOXFINANCE_SFIN0000';
        }

        $configuration->setNordigenCountry($values['country']);
        $configuration->setNordigenBank($values['bank']);

        // save config
        $json = '[]';
        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR, 512);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toArray());
        session()->put(Constants::SELECTED_BANK_COUNTRY, true);

        // send to Nordigen for approval
        return redirect(route('010-build-link.index'));
    }

}
