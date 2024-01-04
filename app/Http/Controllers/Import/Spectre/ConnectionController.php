<?php

/*
 * ConnectionController.php
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

namespace App\Http\Controllers\Import\Spectre;

use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConnectionControllerMiddleware;
use App\Services\Session\Constants;
use App\Services\Spectre\Authentication\SecretManager;
use App\Services\Spectre\Authentication\SecretManager as SpectreSecretManager;
use App\Services\Spectre\Model\Customer;
use App\Services\Spectre\Request\ListConnectionsRequest;
use App\Services\Spectre\Request\ListCustomersRequest;
use App\Services\Spectre\Request\PostConnectSessionsRequest;
use App\Services\Spectre\Request\PostCustomerRequest;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\PostConnectSessionResponse;
use App\Services\Spectre\Response\PostCustomerResponse;
use App\Services\Storage\StorageService;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

/**
 * Class ConnectionController
 */
class ConnectionController extends Controller
{
    use RestoresConfiguration;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(ConnectionControllerMiddleware::class);
        app('view')->share('pageTitle', 'Connection selection      nice ey?');
    }

    /**
     * @return Application|Factory|View
     */
    public function index()
    {
        $mainTitle         = 'Select your financial organisation';
        $subTitle          = 'Select your financial organisation';
        $url               = config('spectre.url');

        $appId             = SecretManager::getAppId();
        $secret            = SecretManager::getSecret();

        // check if already has the correct customer:
        $hasCustomer       = false;
        $request           = new ListCustomersRequest($url, $appId, $secret);
        $list              = $request->get();
        $identifier        = null;

        if ($list instanceof ErrorResponse) {
            throw new ImporterErrorException(sprintf('%s: %s', $list->class, $list->message));
        }

        /** @var Customer $item */
        foreach ($list as $item) {
            if (config('spectre.customer_identifier', 'default_ff3_customer') === $item->identifier) {
                $hasCustomer = true;
                $identifier  = $item->id;
            }
        }

        if (false === $hasCustomer) {
            // create new one
            $request             = new PostCustomerRequest($url, $appId, $secret);
            $request->identifier = config('spectre.customer_identifier', 'default_ff3_customer');

            /** @var PostCustomerResponse $customer */
            $customer            = $request->post();
            $identifier          = $customer->customer->id;
        }

        // store identifier in config
        // skip next time?
        $configuration     = $this->restoreConfiguration();
        $configuration->setIdentifier($identifier);

        // save config
        $json              = '[]';

        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            app('log')->error($e->getMessage());
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toArray());

        app('log')->debug('About to get connections.');
        $request           = new ListConnectionsRequest($url, $appId, $secret);
        $request->customer = $identifier;
        $list              = $request->get();

        if ($list instanceof ErrorResponse) {
            throw new ImporterErrorException(sprintf('%s: %s', $list->class, $list->message));
        }

        return view('import.011-connection.index', compact('mainTitle', 'subTitle', 'list', 'identifier', 'configuration'));
    }

    /**
     * @return Application|Redirector|RedirectResponse
     */
    public function post(Request $request)
    {
        $connectionId  = $request->get('spectre_connection_id');
        $configuration = $this->restoreConfiguration();

        if ('00' === $connectionId) {
            // make a new connection.
            $url                = config('spectre.url');
            $appId              = SpectreSecretManager::getAppId();
            $secret             = SpectreSecretManager::getSecret();
            $newToken           = new PostConnectSessionsRequest($url, $appId, $secret);
            $newToken->customer = $configuration->getIdentifier();
            $newToken->url      = route('011-connections.callback');

            /** @var PostConnectSessionResponse $result */
            $result             = $newToken->post();

            return redirect($result->connect_url);
        }

        // store connection in config, go to fancy JS page.
        // store identifier in config
        // skip next time?
        $configuration = $this->restoreConfiguration();
        $configuration->setConnection($connectionId);

        // save config
        $json          = '[]';

        try {
            $json = json_encode($configuration->toArray(), JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            app('log')->error($e->getMessage());
        }
        StorageService::storeContent($json);

        session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
        session()->put(Constants::CONNECTION_SELECTED_INDICATOR, true);

        // redirect to job configuration
        return redirect(route('004-configure.index'));
    }
}
