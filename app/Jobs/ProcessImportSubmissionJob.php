<?php

declare(strict_types=1);

namespace App\Jobs;

use Exception;
use Throwable;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImportSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries   = 1;

    /**
     * The maximum number of seconds the job can run for.
     *
     * @var int
     */
    public $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $identifier, private Configuration $configuration, private array $transactions, private string $accessToken, private string $baseUrl, private ?string $vanityUrl)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ProcessImportSubmissionJob started', [
            'identifier'        => $this->identifier,
            'transaction_count' => count($this->transactions),
        ]);

        // Validate authentication credentials before proceeding
        if ('' === $this->accessToken) {
            throw new Exception('Access token is empty - cannot authenticate with Firefly III');
        }

        if ('' === $this->baseUrl) {
            throw new Exception('Base URL is empty - cannot connect to Firefly III');
        }

        Log::info('Job authentication credentials validation', [
            'identifier'           => $this->identifier,
            'access_token_length'  => strlen($this->accessToken),
            'access_token_preview' => substr($this->accessToken, 0, 20).'...',
            'base_url'             => $this->baseUrl,
            'vanity_url'           => $this->vanityUrl ?? 'null',
        ]);

        // Backup original configuration
        $originalConfig = [
            'importer.access_token' => config('importer.access_token'),
            'importer.url'          => config('importer.url'),
            'importer.vanity_url'   => config('importer.vanity_url'),
        ];

        Log::debug('Original config backup', [
            'identifier'            => $this->identifier,
            'original_token_length' => strlen(
                (string) $originalConfig['importer.access_token']
            ),
            'original_url'          => $originalConfig['importer.url'],
            'original_vanity'       => $originalConfig['importer.vanity_url'],
        ]);

        try {
            // Set authentication context for this job
            config([
                'importer.access_token' => $this->accessToken,
                'importer.url'          => $this->baseUrl,
                'importer.vanity_url'   => $this->vanityUrl ?? $this->baseUrl,
            ]);

            Log::debug('Authentication context set for job', [
                'identifier'          => $this->identifier,
                'base_url'            => $this->baseUrl,
                'vanity_url'          => $this->vanityUrl ?? $this->baseUrl,
                'access_token_length' => strlen($this->accessToken),
            ]);

            // Verify config was actually set
            $verifyToken = config('importer.access_token');
            $verifyUrl   = config('importer.url');

            Log::debug('Config verification after setting', [
                'identifier'           => $this->identifier,
                'config_token_matches' => $verifyToken === $this->accessToken,
                'config_url_matches'   => $verifyUrl === $this->baseUrl,
                'config_token_length'  => strlen((string) $verifyToken),
                'config_url'           => $verifyUrl,
            ]);

            if ($verifyToken !== $this->accessToken) {
                throw new Exception(
                    'Failed to set access token in config properly'
                );
            }

            if ($verifyUrl !== $this->baseUrl) {
                throw new Exception(
                    'Failed to set base URL in config properly'
                );
            }

            // Set initial running status
            SubmissionStatusManager::setSubmissionStatus(
                SubmissionStatus::SUBMISSION_RUNNING,
                $this->identifier
            );

            // Initialize routine manager and execute import
            $routine     = new RoutineManager($this->identifier);
            $routine->setConfiguration($this->configuration);
            $routine->setTransactions($this->transactions);

            Log::debug('Starting routine execution', [
                'identifier' => $this->identifier,
            ]);

            // Execute the import process
            $routine->start();

            // Set completion status
            SubmissionStatusManager::setSubmissionStatus(
                SubmissionStatus::SUBMISSION_DONE,
                $this->identifier
            );

            Log::info('ProcessImportSubmissionJob completed successfully', [
                'identifier' => $this->identifier,
                'messages'   => count($routine->getAllMessages()),
                'warnings'   => count($routine->getAllWarnings()),
                'errors'     => count($routine->getAllErrors()),
            ]);
        } catch (Throwable $e) {
            Log::error('ProcessImportSubmissionJob failed', [
                'identifier' => $this->identifier,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // Set error status
            SubmissionStatusManager::setSubmissionStatus(
                SubmissionStatus::SUBMISSION_ERRORED,
                $this->identifier
            );

            // Re-throw to mark job as failed
            throw $e;
        } finally {
            // Always restore original configuration
            config($originalConfig);

            Log::debug('Authentication context restored', [
                'identifier' => $this->identifier,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessImportSubmissionJob marked as failed', [
            'identifier' => $this->identifier,
            'exception'  => $exception->getMessage(),
        ]);

        // Ensure error status is set even if job fails catastrophically
        SubmissionStatusManager::setSubmissionStatus(
            SubmissionStatus::SUBMISSION_ERRORED,
            $this->identifier
        );
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
