<?php

/*
 * SubmitController.php
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

use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SubmitControllerMiddleware;
use App\Jobs\ProcessImportSubmissionJob;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class SubmitController
 */
class SubmitController extends Controller
{
    use RestoresConfiguration;

    protected const string DISK_NAME = 'jobs';

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        view()->share('pageTitle', 'Submit data to Firefly III');
        $this->middleware(SubmitControllerMiddleware::class);
    }

    /**
     * @return Application|Factory|View
     */
    public function index()
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $mainTitle     = 'Submit the data';
        $statusManager = new SubmissionStatusManager();
        $configuration = $this->restoreConfiguration();
        $flow          = $configuration->getFlow();
        // The step immediately preceding submit (008) is always convert (007)
        $jobBackUrl    = route('007-convert.index');

        // submission job ID may be in session:
        $identifier    = session()->get(Constants::IMPORT_JOB_IDENTIFIER);
        // if null, create a new one:
        if (null === $identifier) {
            $identifier = $statusManager->generateIdentifier();
        }

        // validate flow
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }

        app('log')->debug(sprintf('Submit (import) routine manager identifier is "%s"', $identifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::IMPORT_JOB_IDENTIFIER, $identifier);
        app('log')->debug(sprintf('Stored "%s" under "%s"', $identifier, Constants::IMPORT_JOB_IDENTIFIER));

        return view('import.008-submit.index', compact('mainTitle', 'identifier', 'jobBackUrl'));
    }

    public function start(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $identifier           = $request->get('identifier');
        if (null === $identifier) {
            app('log')->error('Start: Identifier is NULL');
            $status         = new SubmissionStatus();
            $status->status = SubmissionStatus::SUBMISSION_ERRORED;

            return response()->json($status->toArray());
        }
        $configuration        = $this->restoreConfiguration();
        $routine              = new RoutineManager($identifier);
        app('log')->error('Start: Find import job status.');
        $importJobStatus      = SubmissionStatusManager::startOrFindSubmission($identifier);

        // search for transactions on disk using the import routine's identifier, NOT the submission routine's:
        $conversionIdentifier = session()->get(Constants::CONVERSION_JOB_IDENTIFIER);
        $disk                 = \Storage::disk(self::DISK_NAME);
        $fileName             = sprintf('%s.json', $conversionIdentifier);

        // get files from disk:
        if (!$disk->has($fileName)) {
            Log::error(sprintf('The file "%s" does not exist on the "%s" disk.', $fileName, self::DISK_NAME));
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);

            return response()->json($importJobStatus->toArray());
        }

        try {
            $json         = $disk->get($fileName);
            $transactions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            app('log')->debug(sprintf('Found %d transactions on the drive.', count($transactions)));
        } catch (FileNotFoundException|\JsonException $e) {
            Log::error(sprintf('The file "%s" on "%s" disk contains error: %s', $fileName, self::DISK_NAME, $e->getMessage()));
            // TODO error in logs
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED);

            return response()->json($importJobStatus->toArray());
        }

        // Retrieve authentication credentials for job
        $accessToken = SecretManager::getAccessToken();
        $baseUrl = SecretManager::getBaseUrl();
        $vanityUrl = SecretManager::getVanityUrl();

        // Set initial running status before dispatching job
        SubmissionStatusManager::setSubmissionStatus(
            SubmissionStatus::SUBMISSION_RUNNING,
            $identifier
        );

        // Dispatch asynchronous job for processing
        ProcessImportSubmissionJob::dispatch(
            $identifier,
            $configuration,
            $transactions,
            $accessToken,
            $baseUrl,
            $vanityUrl
        );

        app('log')->debug('ProcessImportSubmissionJob dispatched', [
            'identifier' => $identifier,
            'transaction_count' => count($transactions)
        ]);

        // Return immediate response indicating job was dispatched
        return response()->json([
            'status' => 'job_dispatched',
            'identifier' => $identifier
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $identifier      = $request->get('identifier');
        app('log')->debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        if (null === $identifier) {
            app('log')->warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new SubmissionStatus();

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = SubmissionStatusManager::startOrFindSubmission($identifier);

        return response()->json($importJobStatus->toArray());
    }
}
