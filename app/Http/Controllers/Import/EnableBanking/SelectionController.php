<?php

/*
 * SelectionController.php
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

namespace App\Http\Controllers\Import\EnableBanking;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Http\Controllers\Controller;
use App\Http\Request\SelectionRequest;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\EnableBanking\Request\GetASPSPsRequest;
use App\Services\EnableBanking\Response\ASPSPsResponse;
use App\Services\EnableBanking\Response\ErrorResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Class SelectionController
 */
class SelectionController extends Controller
{
    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    /**
     * Select a country + bank for Enable Banking.
     *
     * @return Factory|RedirectResponse|View
     */
    public function index(Request $request, string $identifier)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $countries = config('enablebanking.countries');
        $mainTitle = 'Select your country and bank';
        $pageTitle = 'Select your country and bank';
        $subTitle = 'Select your country and the bank you wish to use.';
        $importJob = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();

        // Check if we already have a session
        $sessions = $configuration->getEnableBankingSessions();
        $country = $configuration->getEnableBankingCountry();
        $bank = $configuration->getEnableBankingBank();

        if (count($sessions) > 0 && '' !== $country && '' !== $bank) {
            Log::debug('Already have session, redirect to configuration.');

            return redirect(route('configure-import.index', [$identifier]));
        }

        // Get country from query parameter, or from configuration, or default to empty
        $selectedCountry = $request->query('country', $country ?: '');
        $response = null;

        // Only fetch banks if a country is selected
        if ('' !== $selectedCountry) {
            $url = config('enablebanking.url');

            try {
                $bankRequest = new GetASPSPsRequest($url, $selectedCountry);
                $bankRequest->setTimeOut(config('importer.connection.timeout'));
                $response = $bankRequest->get();
            } catch (ImporterHttpException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }

            if ($response instanceof ErrorResponse) {
                throw new ImporterErrorException($response->message);
            }
        }

        $flow = 'eb';

        return view('import.009-selection.index', compact('mainTitle', 'identifier', 'pageTitle', 'subTitle', 'response', 'countries', 'configuration', 'selectedCountry', 'flow'));
    }

    /**
     * @return Redirector|RedirectResponse
     */
    public function postIndex(SelectionRequest $request, string $identifier)
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $importJob = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $values = $request->getAll();

        $configuration->setEnableBankingCountry($values['country']);
        $configuration->setEnableBankingBank($values['bank']);

        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        // Send to Enable Banking for approval
        return redirect(route('eb-connect.index', [$identifier]));
    }
}
