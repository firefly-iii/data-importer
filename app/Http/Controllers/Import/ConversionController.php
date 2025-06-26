<?php

/*
 * ConversionController.php
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

use Throwable;
use Storage;
use JsonException;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ConversionControllerMiddleware;
use App\Services\Camt\Conversion\RoutineManager as CamtRoutineManager;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\Session\Constants;
use App\Services\Shared\Conversion\ConversionStatus;
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

/**
 * Class ConversionController
 */
class ConversionController extends Controller
{
    use RestoresConfiguration;

    protected const DISK_NAME = 'jobs'; // TODO stored in several places

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Importing data...');
        $this->middleware(ConversionControllerMiddleware::class);
    }

    /**
     * @throws ImporterErrorException
     */
    public function index(): Application|Factory|View
    {
        // Log::debug(sprintf('Now in %s', __METHOD__));
        $mainTitle           = 'Convert the data';

        // create configuration:
        $configuration       = $this->restoreConfiguration();

        Log::debug('Will now verify configuration content.');
        $flow                = $configuration->getFlow();

        // default back to mapping
        $jobBackUrl          = route('back.mapping');

        // Set appropriate back URL based on flow
        // SimpleFIN always goes back to configuration
        if ('simplefin' === $flow) {
            $jobBackUrl = route('back.config');
            Log::debug('SimpleFIN: Pressing "back" will send you to configure.');
        }
        // no mapping, back to roles
        if ('simplefin' !== $flow && 0 === count($configuration->getDoMapping()) && 'file' === $flow) {
            Log::debug('Pressing "back" will send you to roles.');
            $jobBackUrl = route('back.roles');
        }
        // back to mapping
        if ('simplefin' !== $flow && 0 === count($configuration->getMapping())) {
            Log::debug('Pressing "back" will send you to mapping.');
            $jobBackUrl = route('back.mapping');
        }
        // TODO option is not used atm.
        //        if (true === $configuration->isMapAllData()) {
        //            Log::debug('Pressing "back" will send you to mapping.');
        //            $jobBackUrl = route('back.mapping');
        //        }

        // job ID may be in session:
        $identifier          = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $routine             = null;
        $flow                = $configuration->getFlow();
        Log::debug('Will redirect to submission after conversion.');
        $nextUrl             = route('008-submit.index');

        // switch based on flow:
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }
        // @var RoutineManagerInterface $routine
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
            Log::debug('Create GoCardless routine manager.');
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            Log::debug('Create Spectre routine manager.');
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('simplefin' === $flow) {
            Log::debug('Create SimpleFIN routine manager.');

            try {
                $routine = new SimpleFINRoutineManager($identifier);
                Log::debug('SimpleFIN routine manager created successfully.');
            } catch (Throwable $e) {
                Log::error(sprintf('Failed to create SimpleFIN routine manager: %s',$e->getMessage()));
                Log::error(sprintf('Error class: %s',$e::class));
                Log::error(sprintf('Error file: %s:%d',$e->getFile(),$e->getLine()));
                Log::error(sprintf('Stack trace: %s',$e->getTraceAsString()));

                throw $e;
            }
        }
        if ($configuration->isMapAllData() && in_array($flow, ['spectre', 'nordigen', 'simplefin'], true)) {
            Log::debug('Will redirect to mapping after conversion.');
            $nextUrl = route('006-mapping.index');
        }
        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }

        // may be a new identifier! Yay!
        $identifier          = $routine->getIdentifier();

        Log::debug(sprintf('Conversion routine manager identifier is "%s"', $identifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::CONVERSION_JOB_IDENTIFIER, $identifier);
        Log::debug(sprintf('Stored "%s" under "%s"', $identifier, Constants::CONVERSION_JOB_IDENTIFIER));

        // Prepare new account creation data for SimpleFIN
        $newAccountsToCreate = [];
        if ('simplefin' === $flow) {
            $accounts    = $configuration->getAccounts();
            $newAccounts = $configuration->getNewAccounts();

            foreach ($accounts as $simplefinAccountId => $fireflyAccountId) {
                if ('create_new' === $fireflyAccountId && array_key_exists($simplefinAccountId, $newAccounts) && null !== $newAccounts[$simplefinAccountId]) {
                    $newAccountsToCreate[$simplefinAccountId] = $newAccounts[$simplefinAccountId];
                }
            }
        }

        return view('import.007-convert.index', compact('mainTitle', 'identifier', 'jobBackUrl', 'flow', 'nextUrl', 'newAccountsToCreate'));
    }

    public function start(Request $request): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $identifier      = $request->get('identifier');
        $configuration   = $this->restoreConfiguration();
        $routine         = null;

        // Validate configuration contract for SimpleFIN before proceeding
        if ('simplefin' === $configuration->getFlow()) {
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
        if ('simplefin' === $configuration->getFlow()) {
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
        $flow            = $configuration->getFlow();
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }
        // @var RoutineManagerInterface $routine
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
            $routine = new NordigenRoutineManager($identifier);
        }
        if ('spectre' === $flow) {
            $routine = new SpectreRoutineManager($identifier);
        }
        if ('simplefin' === $flow) {
            try {
                $routine = new SimpleFINRoutineManager($identifier);
                Log::debug('SimpleFIN routine manager created successfully in start method.');
            } catch (Throwable $e) {
                Log::error(sprintf('Failed to create SimpleFIN routine manager in start method: %s',$e->getMessage()));
                Log::error(sprintf('Error class: %s',$e::class));
                Log::error(sprintf('Error file: %s:%d',$e->getFile(),$e->getLine()));
                Log::error(sprintf('Stack trace: %s',$e->getTraceAsString()));

                throw $e;
            }
        }

        if (null === $routine) {
            throw new ImporterErrorException(sprintf('Could not create routine manager for flow "%s"', $flow));
        }

        $importJobStatus = RoutineStatusManager::startOrFindConversion($identifier);

        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_RUNNING);

        // then push stuff into the routine:
        $routine->setConfiguration($configuration);

        try {
            $transactions = $routine->start();
        } catch (ImporterErrorException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED);

            return response()->json($importJobStatus->toArray());
        }
        Log::debug(sprintf('Conversion routine "%s" was started successfully.', $flow));
        if (0 === count($transactions)) {
            Log::error('[b] Zero transactions!');
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED);

            return response()->json($importJobStatus->toArray());
        }
        Log::debug(sprintf('Conversion routine "%s" yielded %d transaction(s).', $flow, count($transactions)));
        // save transactions in 'jobs' directory under the same key as the conversion thing.
        $disk            = Storage::disk(self::DISK_NAME);

        try {
            $disk->put(sprintf('%s.json', $identifier), json_encode($transactions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            Log::error(sprintf('JSON exception: %s', $e->getMessage()));
            Log::error($e->getTraceAsString());
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED);

            return response()->json($importJobStatus->toArray());
        }
        Log::debug(sprintf('Transactions are stored on disk "%s" in file "%s.json"', self::DISK_NAME, $identifier));

        // set done:
        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_DONE);

        // set config as complete.
        session()->put(Constants::CONVERSION_COMPLETE_INDICATOR, true);
        Log::debug('Set conversion as complete.');

        return response()->json($importJobStatus->toArray());
    }

    public function status(Request $request): JsonResponse
    {
        //        Log::debug(sprintf('Now at %s', __METHOD__));
        $identifier      = $request->get('identifier');
        Log::debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        if (null === $identifier) {
            Log::warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new ConversionStatus();

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = RoutineStatusManager::startOrFindConversion($identifier);

        return response()->json($importJobStatus->toArray());
    }
}
