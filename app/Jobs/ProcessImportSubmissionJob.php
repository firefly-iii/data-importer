<?php

/*
 * ProcessImportSubmissionJob.php
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

namespace App\Jobs;

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries   = 1;
    private ImportJobRepository $repository;

    /**
     * The maximum number of seconds the job can run for.
     */
    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(private ImportJob $importJob, private string $accessToken, private string $baseUrl, private ?string $vanityUrl)
    {
        $this->importJob->refreshInstanceIdentifier();
        $this->repository = new ImportJobRepository();
        $this->repository->saveToDisk($this->importJob);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessImportSubmissionJob started', [
            'identifier'        => $this->importJob->identifier,
            'transaction_count' => count($this->importJob->getConvertedTransactions()),
        ]);

        // Validate authentication credentials before proceeding
        if ('' === $this->accessToken) {
            throw new Exception('Access token is empty - cannot authenticate with Firefly III');
        }

        if ('' === $this->baseUrl) {
            throw new Exception('Base URL is empty - cannot connect to Firefly III');
        }

        try {
            // Set initial running status
            $this->importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_RUNNING);
            $this->repository->saveToDisk($this->importJob);
            // Initialize routine manager and execute import
            $routine         = new RoutineManager($this->importJob);

            Log::debug(sprintf('Starting submission routine execution for import job "%s"', $this->importJob->identifier));

            // Execute the import process
            $routine->start();

            // get the import job back just in case.
            $this->importJob = $routine->getImportJob();

            // Set completion status
            $this->importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_DONE);
            $this->repository->saveToDisk($this->importJob);

            // FIXME no longer necessary to collect all messages etc, it is in the importjob anway.
            Log::info('ProcessImportSubmissionJob completed successfully', ['identifier' => $this->importJob->identifier]);
        } catch (Throwable $e) {
            Log::error('ProcessImportSubmissionJob failed', [
                'identifier' => $this->importJob->identifier,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // Set error status
            $this->importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_ERRORED);
            $this->repository->saveToDisk($this->importJob);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessImportSubmissionJob marked as failed', [
            'identifier' => $this->importJob->identifier,
            'exception'  => $exception->getMessage(),
        ]);

        // Ensure error status is set even if job fails catastrophically
        $this->importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_ERRORED);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['import-submission', $this->identifier];
    }
}
