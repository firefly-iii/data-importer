<?php

/*
 * SubmitController.php
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
use App\Http\Middleware\SubmitControllerMiddleware;
use App\Jobs\ProcessImportSubmissionJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Support\Http\RestoresConfiguration;
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

    private ImportJobRepository $repository;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        view()->share('pageTitle', 'Submit data to Firefly III');
        $this->middleware(SubmitControllerMiddleware::class);
        $this->repository = new ImportJobRepository();
    }

    /**
     * @return Application|Factory|View
     */
    public function index(string $identifier)
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $mainTitle     = 'Submit the data';
        $importJob     = $this->repository->find($identifier);
        $statusManager = new SubmissionStatusManager();
        $flow          = $importJob->getFlow();

        if ('ready_for_submission' !== $importJob->getState()) {
            die(sprintf('Job is in state "%s", expected ready_for_submission.', $importJob->getState()));
        }

        // The step immediately preceding submit (008) is always convert (007)
        $jobBackUrl = route('data-conversion.index', [$identifier]);

        // validate flow
        if (!in_array($flow, config('importer.flows'), true)) {
            throw new ImporterErrorException(sprintf('Not a supported flow: "%s"', $flow));
        }

        Log::debug(sprintf('Submit (import) routine manager identifier is "%s"', $identifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::IMPORT_JOB_IDENTIFIER, $identifier);
        Log::debug(sprintf('Stored "%s" under "%s"', $identifier, Constants::IMPORT_JOB_IDENTIFIER));

        return view('import.008-submit.index', compact('mainTitle', 'identifier', 'jobBackUrl'));
    }

    public function start(Request $request, string $identifier): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        Log::error('Start: Find import job status.');

        // search for transactions on disk using the import routine's identifier, NOT the submission routine's:
        $transactions = $importJob->getConvertedTransactions();

        // Retrieve authentication credentials for job
        $accessToken = SecretManager::getAccessToken();
        $baseUrl     = SecretManager::getBaseUrl();
        $vanityUrl   = SecretManager::getVanityUrl();

        // Set initial running status before dispatching job
        $importJob->submissionStatus->status = SubmissionStatus::SUBMISSION_RUNNING;
        $this->repository->saveToDisk($importJob);

        // Dispatch asynchronous job for processing
        ProcessImportSubmissionJob::dispatch(
            $identifier,
            $configuration,
            $transactions,
            $accessToken,
            $baseUrl,
            $vanityUrl
        );

        // Return immediate response indicating job was dispatched
        return response()->json(['status' => SubmissionStatus::SUBMISSION_RUNNING, 'identifier' => $identifier,]);
    }

    public function status(Request $request, string $identifier): JsonResponse
    {
        $importJob = $this->repository->find($identifier);
        Log::debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));
        return response()->json($importJob->submissionStatus);
    }
}
