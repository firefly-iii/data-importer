<?php
/*
 * LinkController.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Nordigen importer
 * (https://github.com/firefly-iii/nordigen-importer).
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
use App\Http\Controllers\Controller;
use App\Http\Middleware\LinkControllerMiddleware;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Nordigen\Request\PostNewRequisitionRequest;
use App\Services\Nordigen\Response\NewRequisitionResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Session\Constants;
use Illuminate\Http\Request;
use Log;
use Ramsey\Uuid\Uuid;

/**
 * Class LinkController
 */
class LinkController extends Controller
{

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(LinkControllerMiddleware::class);
    }

    /**
     */
    public function build()
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        // grab config of user:
        // create a new config thing
        $configuration = Configuration::fromArray([]);
        if (session()->has(Constants::CONFIGURATION)) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        }
        if ('XX' === $configuration->getNordigenBank()) {
            return redirect(route('back.selection'));
        }

        TokenManager::validateAllTokens();

        // create and save local reference:
        $uuid = Uuid::uuid4()->toString();

        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $request     = new PostNewRequisitionRequest($url, $accessToken);
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setBank($configuration->getNordigenBank());
        $request->setReference($uuid);

        Log::debug(sprintf('Reference is %s', $uuid));

        /** @var NewRequisitionResponse $response */
        $response = $request->post();
        Log::debug(sprintf('Got a new requisition with id %s', $response->id));
        Log::debug(sprintf('Status: %s, returned reference: %s', $response->status, $response->reference));
        Log::debug(sprintf('Will now redirect the user to %s', $response->link));

        // save config!
        $configuration->addRequisition($uuid, $response->id);
        session()->put(Constants::CONFIGURATION, $configuration->toArray());

        return redirect($response->link);

    }

    /**
     * @param Request $request
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function callback(Request $request)
    {
        $reference = $request->get('ref');
        Log::debug(sprintf('Now at %s', __METHOD__));
        Log::debug(sprintf('Reference is %s', $reference));

        // create a new config thing
        $configuration = Configuration::fromArray([]);
        if (session()->has(Constants::CONFIGURATION)) {
            $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));
        }
        $requisition = $configuration->getRequisition($reference);
        if (null === $requisition) {
            throw new ImporterErrorException('No such requisition.');
        }
        // continue!
        session()->put(Constants::REQUISITION_REFERENCE, $reference);
        return redirect(route('004-configure.index'));
    }
}
