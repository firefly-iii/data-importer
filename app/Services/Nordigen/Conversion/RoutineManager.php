<?php

/*
 * RoutineManager.php
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

namespace App\Services\Nordigen\Conversion;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Nordigen\Conversion\Routine\FilterTransactions;
use App\Services\Nordigen\Conversion\Routine\GenerateTransactions;
use App\Services\Nordigen\Conversion\Routine\TransactionProcessor;
use App\Services\Nordigen\Request\Request;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CreatesAccounts;

    private Configuration        $configuration;
    private FilterTransactions   $transactionFilter;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;
    private ImportJobRepository  $repository;
    private ImportJob            $importJob;

    private array $downloaded;

    public function __construct(ImportJob $importJob)
    {
        $this->downloaded           = [];
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->transactionFilter    = new FilterTransactions();
        $this->repository           = new ImportJobRepository();
        $this->importJob            = $importJob;
        $this->importJob->refreshInstanceIdentifier();
        $this->setConfiguration($this->importJob->getConfiguration());
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        Log::debug(sprintf('RoutineManager.getServiceAccounts(%d)', count($this->importJob->getServiceAccounts())));

        return $this->importJob->getServiceAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    private function setConfiguration(Configuration $configuration): void
    {
        Log::debug('RoutineManager.setConfiguration');
        // save config
        $this->configuration = $configuration;

        // Step 0: configuration validation.
        // $this->validateAccounts();

        // share config
        $this->transactionProcessor->setImportJob($this->importJob);
        $this->transactionGenerator->setImportJob($this->importJob);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        Log::debug(sprintf('The GoCardless API URL is %s', config('nordigen.url')));
        $this->transactionProcessor->setExistingServiceAccounts($this->getServiceAccounts());
        // Step 1: get transactions from GoCardless
        $this->downloadFromGoCardless();

        // Step 2: collect rate limits from the transaction processor.
        $this->collectRateLimits();

        // Step 3: Generate Firefly III-ready transactions.
        // first collect target accounts from Firefly III.
        $this->collectTargetAccounts();

        // then check for possible rate limit-related errors
        $this->reportRateLimits();

        // then report and stop if nothing was even downloaded
        if (true === $this->breakOnDownload()) {
            return [];
        }

        // then collect more account infro, from GoCardless.
        $this->collectGoCardlessAccounts();

        // then generate the transactions
        $transactions = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        // filter the transactions
        $filtered     = $this->transactionFilter->filter($transactions);
        Log::debug(sprintf('Filtered down to %d Firefly III transactions.', count($filtered)));

        // return everything.
        return $filtered;
    }

    private function generateRateLimitMessage(array $account, array $rateLimit): string
    {
        Log::debug('generateRateLimitMessage');
        $message = '';
        if (0 === $rateLimit['remaining'] && $rateLimit['reset'] > 1) {
            $message = sprintf('You have no requests left for bank account "%s"', $account['name']);

            // add IBAN if present
            if (array_key_exists('iban', $account) && '' !== (string)$account['iban']) {
                $message .= sprintf(' (IBAN %s)', $account['iban']);
            }

            // add account number if present
            if (array_key_exists('number', $account) && '' !== (string)$account['number']) {
                $message .= sprintf(' (account number %s)', $account['number']);
            }
            $message .= sprintf('. The limit resets in %s. ', Request::formatTime($rateLimit['reset']));
        }
        if ($rateLimit['remaining'] > 0) {
            $message = sprintf('You have %d request(s) left for bank account "%s"', $rateLimit['remaining'], $account['name']);

            // add IBAN if present
            if (array_key_exists('iban', $account) && '' !== (string)$account['iban']) {
                $message .= sprintf(' (IBAN %s)', $account['iban']);
            }

            // add account number if present
            if (array_key_exists('number', $account) && '' !== (string)$account['number']) {
                $message .= sprintf(' (account number %s)', $account['number']);
            }
            $message .= '. ';
        }
        $message .= '[Read more about GoCardless rate limits](https://docs.firefly-iii.org/references/faq/data-importer/salt-edge-gocardless/#i-am-rate-limited-by-gocardless).';
        Log::debug(sprintf('Generated rate limit message: %s', $message));

        return $message;
    }

    private function findAccountInfo(array $accounts, int $accountId): ?array
    {
        return array_find($accounts, fn ($account) => $account['id'] === $accountId);

    }

    /**
     * @throws ImporterErrorException
     */
    private function downloadFromGoCardless(): void
    {
        Log::debug('Call transaction processor download.');

        try {
            $this->downloaded = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            Log::error('Could not download transactions from GoCardless.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            // add error to current error thing:
            $this->importJob->conversionStatus->addError(0, sprintf('[a109]: Could not download from GoCardless: %s', $e->getMessage()));
            $this->repository->saveToDisk($this->importJob);

            throw $e;
        }
        // get updated import job from transaction processor, and spread it to others.
        $this->importJob = $this->transactionProcessor->getImportJob();
        $this->setConfiguration($this->importJob->getConfiguration());
        $this->repository->saveToDisk($this->importJob);
    }

    private function collectRateLimits(): void
    {
        // collect accounts from the configuration, and join them with the rate limits
        $configAccounts = $this->configuration->getAccounts();
        foreach ($this->importJob->conversionStatus->rateLimits as $account => $rateLimit) {
            Log::debug(sprintf('Rate limit for account %s: %d request(s) left, %d second(s)', $account, $rateLimit['remaining'], $rateLimit['reset']));
            if (!array_key_exists($account, $configAccounts)) {
                Log::error(sprintf('Account "%s" was not found in your configuration.', $account));

                continue;
            }
            $this->rateLimits[$configAccounts[$account]] = $rateLimit;
        }
    }

    private function collectTargetAccounts(): void
    {
        Log::debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->importJob->conversionStatus->addError(0, sprintf('[a110]: Error while collecting target accounts: %s', $e->getMessage()));
            $this->repository->saveToDisk($this->importJob);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
    }

    private function reportRateLimits(): void
    {
        // Grab Firefly III accounts from the transaction generator.
        $userAccounts = $this->transactionGenerator->getUserAccounts();

        // now we can report on target limits:
        Log::debug('[a] Add message about rate limits.');
        foreach ($this->importJob->conversionStatus->rateLimits as $accountId => $rateLimit) {
            // do not report if the remaining value is zero, but the reset time 1 or less.
            // this seems to be some kind of default value.
            // change: do not report when the reset time is less than 60 seconds.
            if ($rateLimit['reset'] <= 60) {
                Log::debug(sprintf('Account "%s" has no interesting rate limit information.', $accountId));

                continue;
            }

            Log::debug(sprintf('Add message about rate limits for account %s.', $accountId));
            $fireflyIIIAccount = $this->findAccountInfo($userAccounts, $accountId);
            if (null === $fireflyIIIAccount) {
                Log::debug('Found NO Firefly III account to report on, will not report rate limit.');

                continue;
            }
            Log::debug(sprintf('Found Firefly III account #%d ("%s") to report on.', $fireflyIIIAccount['id'], $fireflyIIIAccount['name']));
            $message           = $this->generateRateLimitMessage($fireflyIIIAccount, $rateLimit);
            if (0 === $rateLimit['remaining']) {
                $this->importJob->conversionStatus->addWarning(0, $message);
            }
            if ($rateLimit['remaining'] > 0 && $rateLimit['remaining'] <= 3) {
                $this->importJob->conversionStatus->addRateLimit(0, $message);
            }
        }
    }

    private function breakOnDownload(): bool
    {
        $total = 0;
        foreach ($this->downloaded as $transactions) {
            $total += count($transactions);
        }
        if (0 === $total) {
            Log::warning('Downloaded nothing, will return nothing.');
            // add error to current error thing:
            $this->importJob->conversionStatus->addError(0, '[a111]: No transactions were downloaded from GoCardless. You may be rate limited or something else went wrong.');
            $this->repository->saveToDisk($this->importJob);

            return true;
        }

        return false;
    }

    private function collectGoCardlessAccounts(): void
    {
        try {
            $this->transactionGenerator->collectNordigenAccounts();
        } catch (ImporterErrorException $e) {
            Log::error('Could not collect info on all GoCardless accounts, but this info isn\'t used at the moment anyway.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
        } catch (AgreementExpiredException $e) {
            $this->importJob->conversionStatus->addError(0, '[a112]: The connection between your bank and GoCardless has expired.');
            $this->repository->saveToDisk($this->importJob);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
        }
    }

    //    /**
    //     * @throws ImporterErrorException
    //     */
    //    private function validateAccounts(): void
    //    {
    //        Log::debug('Validating accounts in configuration.');
    //        $accounts = $this->configuration->getAccounts();
    //        foreach ($accounts as $key => $accountId) {
    //            if (0 === (int)$accountId) {
    //                // throw new ImporterErrorException(sprintf('Cannot import GoCardless account "%s" into Firefly III account #%d. Recreate your configuration file.', $key, $accountId));
    //            }
    //        }
    //    }
    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
