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
use App\Jobs\ProcessImportSubmissionJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class SubmitController
 */
class SubmitController extends Controller
{
    protected const string DISK_NAME = 'jobs';

    private ImportJobRepository $repository;

    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        view()->share('pageTitle', 'Submit data to Firefly III');
        $this->repository = new ImportJobRepository();
    }

    public function index(string $identifier)
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $mainTitle  = 'Submit the data';
        $importJob  = $this->repository->find($identifier);
        $flow       = $importJob->getFlow();

        if ('ready_for_submission' !== $importJob->getState()) {
            exit(sprintf('Job is in state "%s", expected ready_for_submission.', $importJob->getState()));
        }

        // The step immediately preceding submit (008) is always convert (007)
        $jobBackUrl = route('data-conversion.index', [$identifier]);

        // validate flow
        $enabled = config(sprintf('importer.enabled_flows.%s', $flow));
        if (null === $enabled || false === $enabled) {
            throw new ImporterErrorException(sprintf('[c] Not a supported flow: "%s"', $flow));
        }

        Log::debug(sprintf('Submit (import) routine manager identifier is "%s"', $identifier));

        return view('import.008-submit.index', compact('mainTitle', 'identifier', 'jobBackUrl'));
    }

    public function start(Request $request, string $identifier): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $importJob   = $this->repository->find($identifier);
        Log::error('Start: Find import job status.');

        // Retrieve authentication credentials for job
        $accessToken = SecretManager::getAccessToken();
        $baseUrl     = SecretManager::getBaseUrl();
        $vanityUrl   = SecretManager::getVanityUrl();

        // Set initial running status before dispatching job
        $importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_RUNNING);
        $this->repository->saveToDisk($importJob);

        // Dispatch asynchronous job for processing
        ProcessImportSubmissionJob::dispatch($importJob, $accessToken, $baseUrl, $vanityUrl);

        // Return immediate response indicating job was dispatched
        return response()->json(['status' => SubmissionStatus::SUBMISSION_RUNNING, 'identifier' => $identifier]);
    }

    public function status(Request $request, string $identifier): JsonResponse
    {
        $importJob = $this->repository->find($identifier);
        Log::debug(sprintf('Now at %s(%s)', __METHOD__, $identifier));

        return response()->json($importJob->submissionStatus->toArray());
    }
}
