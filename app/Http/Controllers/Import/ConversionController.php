<?php

/*
 * ConversionController.php
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
use App\Http\Middleware\ConversionControllerMiddleware;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Camt\Conversion\RoutineManager as CamtRoutineManager;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\LunchFlow\Conversion\RoutineManager as LunchFlowRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\SimpleFIN\Conversion\RoutineManager as SimpleFINRoutineManager;
use App\Services\Spectre\Conversion\RoutineManager as SpectreRoutineManager;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class ConversionController
 */
class ConversionController extends Controller
{
    use RestoresConfiguration;

    protected const string DISK_NAME = 'jobs'; // TODO stored in several places
    private ImportJobRepository $repository;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Importing data...');
        $this->middleware(ConversionControllerMiddleware::class)->except(['status', 'start']);
        $this->repository = new ImportJobRepository();
    }

    /**
     * @throws ImporterErrorException
     */
    public function index(string $identifier): Application|Factory|View
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $mainTitle           = 'Convert the data';
        $importJob           = $this->repository->find($identifier);
        $configuration       = $importJob->getConfiguration();
        $flow                = $importJob->getFlow();
        $newAccountsToCreate = [];
        // default back to mapping
        $jobBackUrl          = $this->getJobBackUrl($flow, $identifier);
        $flow                = $importJob->getFlow();

        $nextUrl             = route('submit-data.index', [$identifier]);
        // next URL is different when it's not a file flow (in those cases, its mapping)
        if ('file' !== $flow) {
            $nextUrl = route('data-mapping.index', [$identifier]);
        }

        // switch based on flow:
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }

        /** @var null|RoutineManagerInterface $routine */
        if ('file' === $flow) {
            $contentType = $configuration->getContentType();
            if ('unknown' === $contentType || 'csv' === $contentType) {
                Log::debug('Create CSV routine manager.');
                $routine = new CSVRoutineManager($identifier);
            }
            if ('camt' === $contentType) {
                Log::debug('Create CAMT routine manager.');
                $routine = new CamtRoutineManager($identifier);
            }
        }
        if ('nordigen' === $flow) {
            // Prepare new account creation data for Gocardless.
            $newAccountsToCreate = $configuration->getNewAccounts();
            Log::debug('Create GoCardless routine manager.');
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            throw new ImporterErrorException('Need to handle spectre');
            Log::debug('Create Spectre routine manager.');
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('lunchflow' === $flow) {
            throw new ImporterErrorException('Need to handle lunchflow');
            Log::debug('Create Lunch Flow routine manager.');
            $routine = new LunchFlowRoutineManager($identifier);
        }
        if ('simplefin' === $flow) {
            Log::debug('Create SimpleFIN routine manager.');
            // Prepare new account creation data for SimpleFIN
            $newAccountsToCreate = $configuration->getNewAccounts();
            $routine             = new SimpleFINRoutineManager($identifier);
        }

        if ($configuration->isMapAllData() && in_array($flow, ['spectre', 'nordigen', 'simplefin'], true)) {
            throw new ImporterErrorException('Need to handle redirect.');
            Log::debug('Will redirect to mapping after conversion.');
            $nextUrl = route('006-mapping.index');
        }
        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }

        return view('import.007-convert.index', compact('mainTitle', 'identifier', 'jobBackUrl', 'flow', 'nextUrl', 'newAccountsToCreate'));
    }

    public function start(Request $request, string $identifier): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob                           = $this->repository->find($identifier);
        $configuration                       = $importJob->getConfiguration();
        $routine                             = null;

        // Handle new account data for SimpleFIN
        $flow = $importJob->getFlow();
        if ('simplefin' === $flow || 'nordigen' === $flow) {
            $newAccountData = $request->get('new_account_data', []);
            if (count($newAccountData) > 0) {
                Log::debug('Updating configuration with detailed new account data', $newAccountData);

                // Update the configuration with the detailed account creation data
                $existingNewAccounts = $configuration->getNewAccounts();
                foreach ($newAccountData as $accountId => $accountDetails) {
                    if (array_key_exists($accountId, $existingNewAccounts) && null !== $existingNewAccounts[$accountId]) {
                        // Merge the detailed data with existing data
                        $existingNewAccounts[$accountId] = array_merge(
                            $existingNewAccounts[$accountId],
                            [
                                'name'            => $accountDetails['name'],
                                'type'            => $accountDetails['type'],
                                'currency'        => $accountDetails['currency'],
                                'opening_balance' => $accountDetails['opening_balance'],
                            ]
                        );
                    }
                }
                $configuration->setNewAccounts($existingNewAccounts);
            }
        }
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        // now create the right class:
        $flow                                = $importJob->getFlow();
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }

        /** @var null|RoutineManagerInterface $routine */
        if ('file' === $flow) {
            $contentType = $configuration->getContentType();
            if ('unknown' === $contentType || 'csv' === $contentType) {
                $routine = new CSVRoutineManager($identifier);
            }
            if ('camt' === $contentType) {
                $routine = new CamtRoutineManager($identifier);
            }
        }
        if ('simplefin' === $flow) {
            $routine = new SimpleFINRoutineManager($identifier);
            Log::debug('SimpleFIN routine manager created successfully in start method.');
        }

        if ('nordigen' === $flow) {
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            throw new ImporterErrorException('cannot go here B');
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('lunchflow' === $flow) {
            throw new ImporterErrorException('cannot go here C');
            $routine = new LunchFlowRoutineManager($identifier);
        }

        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }
        $importJob->conversionStatus->status = ConversionStatus::CONVERSION_RUNNING;
        $this->repository->saveToDisk($importJob);

        try {
            $transactions = $routine->start();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            Log::error($e->getTraceAsString());

            $importJob->conversionStatus->status = ConversionStatus::CONVERSION_ERRORED;
            $this->repository->saveToDisk($importJob);

            return response()->json($importJob->conversionStatus->toArray());
        }

        Log::debug(sprintf('Conversion routine "%s" was started successfully.', $flow));
        if (0 === count($transactions)) {
            // #10590 do not error out if no transactions are found.
            Log::warning('[b] Zero transactions found during conversion. Will not error out.');

            $importJob->conversionStatus->status = ConversionStatus::CONVERSION_DONE;
            $this->repository->saveToDisk($importJob);

            // return response()->json($importJobStatus->toArray());
        }
        Log::debug(sprintf('Conversion routine "%s" yielded %d transaction(s).', $flow, count($transactions)));
        $importJob->setConvertedTransactions($transactions);


        if ('file' !== $flow) {
            // all other workflows go to mapping (if requested from configuration?)
            $importJob->setState('configured_and_roles_defined');
        }
        if ('file' === $flow) {
            $importJob->setState('ready_for_submission');
        }

        $importJob->conversionStatus->status = ConversionStatus::CONVERSION_DONE;
        $this->repository->saveToDisk($importJob);

        return response()->json($importJob->conversionStatus->toArray());
    }

    public function status(Request $request, string $identifier): JsonResponse
    {
        $importJob = $this->repository->find($identifier);

        return response()->json($importJob->conversionStatus->toArray());
    }

    private function getJobBackUrl(string $flow, string $identifier): string
    {
        $jobBackUrl = route('configure-roles.index', [$identifier]);

        // Set appropriate back URL based on flow
        // All flows but the file flow go back to configuration
        if ('file' !== $flow) {
            $jobBackUrl = route('configure-import.index', [$identifier]);
        }

        return $jobBackUrl;
    }
}
