<?php

/*
 * LinkController.php
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

namespace App\Http\Controllers\Import\Nordigen;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\LinkControllerMiddleware;
use App\Services\Nordigen\Request\GetRequisitionRequest;
use App\Services\Nordigen\Request\PostNewRequisitionRequest;
use App\Services\Nordigen\Request\PostNewUserAgreement;
use App\Services\Nordigen\Response\GetRequisitionResponse;
use App\Services\Nordigen\Response\NewRequisitionResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Ramsey\Uuid\Uuid;

/**
 * Class LinkController
 */
class LinkController extends Controller
{
    use RestoresConfiguration;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(LinkControllerMiddleware::class);
    }

    /**
     * @throws ImporterHttpException
     */
    public function build()
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        // grab config of user:
        // create a new config thing
        $configuration     = $this->restoreConfiguration();
        if ('XX' === $configuration->getNordigenBank()) {
            return redirect(route('back.selection'));
        }

        TokenManager::validateAllTokens();

        // if already a requisition in config file, no need to make a new one unless its invalid.
        $requisitions      = $configuration->getNordigenRequisitions();
        if (1 === count($requisitions)) {
            $url         = config('nordigen.url');
            $accessToken = TokenManager::getAccessToken();
            $reference   = array_shift($requisitions);
            $request     = new GetRequisitionRequest($url, $accessToken, $reference);
            $request->setTimeOut(config('importer.connection.timeout'));

            /** @var GetRequisitionResponse $result */
            $result      = $request->get();

            $configuration->setAccounts($result->accounts);

            session()->put(Constants::REQUISITION_REFERENCE, $reference);

            return redirect(route('004-configure.index'));
        }

        $uuid              = Uuid::uuid4()->toString();
        $url               = config('nordigen.url');
        $accessToken       = TokenManager::getAccessToken();

        $agreementRequest  = new PostNewUserAgreement($url, $accessToken);
        $agreementRequest->setTimeOut(config('importer.connection.timeout'));
        $agreementRequest->setBank($configuration->getNordigenBank());
        $agreementRequest->setAccessValidForDays('90');
        $agreementRequest->setMaxHistoricalDays($configuration->getNordigenMaxDays());
        $agreementResponse = $agreementRequest->post();

        $request           = new PostNewRequisitionRequest($url, $accessToken);
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setBank($configuration->getNordigenBank());
        $request->setReference($uuid);
        $request->setAgreement($agreementResponse->id);

        app('log')->debug(sprintf('Reference is "%s"', $uuid));

        /** @var NewRequisitionResponse $response */
        $response          = $request->post();
        app('log')->debug(sprintf('Got a new requisition with id "%s"', $response->id));
        app('log')->debug(sprintf('Status: %s, returned reference: "%s"', $response->status, $response->reference));
        app('log')->debug(sprintf('Will now redirect the user to %s', $response->link));

        // save config!
        $configuration->addRequisition($uuid, $response->id);

        session()->put(Constants::CONFIGURATION, $configuration->toArray());

        return redirect($response->link);
    }

    /**
     * @return Application|Redirector|RedirectResponse
     */
    public function callback(Request $request)
    {
        $reference     = (string)$request->get('ref');
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        app('log')->debug(sprintf('Reference is "%s"', $reference));

        if ('' === $reference) {
            throw new ImporterHttpException('The reference returned by Nordigen was unexpectedly empty.');
        }

        // create a new config thing
        $configuration = $this->restoreConfiguration();
        $requisition   = $configuration->getRequisition($reference);
        if (null === $requisition) {
            throw new ImporterErrorException('No such requisition.');
        }
        // continue!
        session()->put(Constants::REQUISITION_REFERENCE, $reference);

        return redirect(route('004-configure.index'));
    }
}
