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
use App\Services\Session\Constants;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\SimpleFIN\Conversion\RoutineManager as SimpleFINRoutineManager;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use App\Services\Spectre\Conversion\RoutineManager as SpectreRoutineManager;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $mainTitle     = 'Convert the data';
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $flow          = $importJob->getFlow();

        // default back to mapping
        $jobBackUrl = $this->getJobBackUrl($flow, $identifier);
        $flow       = $importJob->getFlow();

        Log::debug('Will redirect to submission after conversion.');
        $nextUrl = route('008-submit.index');

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
            die('a');
            Log::debug('Create GoCardless routine manager.');
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            die('b');
            Log::debug('Create Spectre routine manager.');
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('lunchflow' === $flow) {
            die('c');
            Log::debug('Create Lunch Flow routine manager.');
            $routine = new LunchFlowRoutineManager($identifier);
        }
        if ('simplefin' === $flow) {
            die('d');
            Log::debug('Create SimpleFIN routine manager.');

            try {
                $routine = new SimpleFINRoutineManager($identifier);
                Log::debug('SimpleFIN routine manager created successfully.');
            } catch (Throwable $e) {
                Log::error(sprintf('Failed to create SimpleFIN routine manager: %s', $e->getMessage()));
                Log::error(sprintf('Error class: %s', $e::class));
                Log::error(sprintf('Error file: %s:%d', $e->getFile(), $e->getLine()));
                Log::error(sprintf('Stack trace: %s', $e->getTraceAsString()));

                throw $e;
            }
        }
        if ($configuration->isMapAllData() && in_array($flow, ['spectre', 'nordigen', 'simplefin'], true)) {
            die('e');
            Log::debug('Will redirect to mapping after conversion.');
            $nextUrl = route('006-mapping.index');
        }
        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }

        // Prepare new account creation data for SimpleFIN
        // FIXME restore the code below

//        $newAccountsToCreate = [];
//        if ('simplefin' === $flow) {
//            $accounts    = $configuration->getAccounts();
//            $newAccounts = $configuration->getNewAccounts();
//
//            foreach ($accounts as $simplefinAccountId => $fireflyAccountId) {
//                if ('create_new' === $fireflyAccountId && array_key_exists($simplefinAccountId, $newAccounts) && null !== $newAccounts[$simplefinAccountId]) {
//                    $newAccountsToCreate[$simplefinAccountId] = $newAccounts[$simplefinAccountId];
//                }
//            }
//        }
        // FIXME restore the code above.
        $newAccountsToCreate = [];

        return view('import.007-convert.index', compact('mainTitle', 'identifier', 'jobBackUrl', 'flow', 'nextUrl', 'newAccountsToCreate'));
    }

    public function start(Request $request, string $identifier): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $routine       = null;

        // Validate configuration contract for SimpleFIN before proceeding
        if ('simplefin' === $importJob->getFlow()) {
            die('why another validation come on');
            $validator          = new ConfigurationContractValidator();
            $contractValidation = $validator->validateConfigurationContract($configuration);

            if (!$contractValidation->isValid()) {
                Log::error('SimpleFIN configuration contract validation failed during conversion start', $contractValidation->getErrors());
                RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED);

                $importJobStatus = RoutineStatusManager::startOrFindConversion($identifier);

                return response()->json($importJobStatus->toArray());
            }

            if ($contractValidation->hasWarnings()) {
                Log::warning('SimpleFIN configuration contract warnings during conversion start', $contractValidation->getWarnings());
            }

            Log::debug('SimpleFIN configuration contract validation successful for conversion start');
        }

        // Handle new account data for SimpleFIN
        if ('simplefin' === $importJob->getFlow()) {
            die('create new accounts');
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

                // Update session with new configuration
                session()->put(Constants::CONFIGURATION, $configuration->toSessionArray());
            }
        }

        // now create the right class:
        $flow = $importJob->getFlow();
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
                $routine = new CamtRoutineManager($identifier); // why do we need this one?
            }
        }
        if ('nordigen' === $flow) {
            die('cannot do this a');
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            die('cannot do this b');
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('lunchflow' === $flow) {
            die('cannot do this c');
            $routine = new LunchFlowRoutineManager($identifier);
        }
        if ('simplefin' === $flow) {
            die('cannot do this e');
            try {
                $routine = new SimpleFINRoutineManager($identifier);
                Log::debug('SimpleFIN routine manager created successfully in start method.');
            } catch (Throwable $e) {
                Log::error(sprintf('Failed to create SimpleFIN routine manager in start method: %s', $e->getMessage()));
                Log::error(sprintf('Error class: %s', $e::class));
                Log::error(sprintf('Error file: %s:%d', $e->getFile(), $e->getLine()));
                Log::error(sprintf('Stack trace: %s', $e->getTraceAsString()));

                throw $e;
            }
        }

        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }
        $conversionStatus         = $importJob->getConversionStatus();
        $conversionStatus->status = ConversionStatus::CONVERSION_RUNNING;
        $importJob->setConversionStatus($conversionStatus);
        $this->repository->saveToDisk($importJob);

        // then push stuff into the routine:
        $routine->setConfiguration($configuration);

        try {
            $transactions = $routine->start();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            Log::error($e->getTraceAsString());

            $conversionStatus->status = ConversionStatus::CONVERSION_ERRORED;
            $importJob->setConversionStatus($conversionStatus);
            $this->repository->saveToDisk($importJob);

            return response()->json($conversionStatus->toArray());
        }

        Log::debug(sprintf('Conversion routine "%s" was started successfully.', $flow));
        if (0 === count($transactions)) {
            // #10590 do not error out if no transactions are found.
            Log::warning('[b] Zero transactions found during conversion. Will not error out.');

            $conversionStatus->status = ConversionStatus::CONVERSION_DONE;
            $importJob->setConversionStatus($conversionStatus);
            $this->repository->saveToDisk($importJob);

            // return response()->json($importJobStatus->toArray());
        }
        Log::debug(sprintf('Conversion routine "%s" yielded %d transaction(s).', $flow, count($transactions)));
        $importJob->setConvertedTransactions($transactions);

        $conversionStatus->status = ConversionStatus::CONVERSION_DONE;
        $importJob->setConversionStatus($conversionStatus);
        $this->repository->saveToDisk($importJob);

        return response()->json($conversionStatus->toArray());
    }

    public function status(Request $request, string $identifier): JsonResponse
    {
        $importJob = $this->repository->find($identifier);
        return response()->json($importJob->getConversionStatus()->toArray());
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
