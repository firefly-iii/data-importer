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

namespace App\Services\LunchFlow\Conversion;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Services\LunchFlow\Conversion\Routine\GenerateTransactions;
use App\Services\LunchFlow\Conversion\Routine\TransactionProcessor;
use App\Services\LunchFlow\Request\Request;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CombinedProgressInformation;
    use GeneratesIdentifier;
    use IsRunningCli;
    use ProgressInformation;

    private Configuration        $configuration;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;

    private array $downloaded;

    public function __construct(?string $identifier)
    {
        $this->allErrors            = [];
        $this->allWarnings          = [];
        $this->allMessages          = [];
        $this->allRateLimits        = [];
        $this->downloaded           = [];

        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return $this->transactionProcessor->getAccounts();
    }

    /**
     * @throws ImporterErrorException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;

        // Step 0: configuration validation.
        $this->validateAccounts();

        // share config
        $this->transactionProcessor->setConfiguration($configuration);
        $this->transactionGenerator->setConfiguration($configuration);

        // set identifier
        $this->transactionProcessor->setIdentifier($this->identifier);
        $this->transactionGenerator->setIdentifier($this->identifier);
    }

    /**
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        Log::debug(sprintf('The Lunch Flow API URL is %s', config('nordigen.url')));

        // Step 1: get transactions from Lunch Flow
        $this->downloadFromLunchFlow();

        // Step 3: Generate Firefly III-ready transactions.
        // first collect target accounts from Firefly III.
        $this->collectTargetAccounts();


        // then report and stop if nothing was even downloaded
        if (true === $this->breakOnDownload()) {
            return [];
        }

        // then generate the transactions
        $transactions = $this->transactionGenerator->getTransactions($this->downloaded);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        // collect errors from transactionProcessor.
        $this->mergeMessages(count($transactions));
        $this->mergeWarnings(count($transactions));
        $this->mergeErrors(count($transactions));

        // return everything.
        return $transactions;
    }

    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays(
            [
                $this->getMessages(),
                $this->transactionGenerator->getMessages(),
                $this->transactionProcessor->getMessages(),
            ],
            $count
        );
    }

    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays(
            [
                $this->getWarnings(),
                $this->transactionGenerator->getWarnings(),
                $this->transactionProcessor->getWarnings(),
            ],
            $count
        );
    }

    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays(
            [
                $this->getErrors(),
                $this->transactionGenerator->getErrors(),
                $this->transactionProcessor->getErrors(),
            ],
            $count
        );
    }


    /**
     * @throws ImporterErrorException
     */
    private function downloadFromLunchFlow(): void
    {
        Log::debug('Call transaction processor download.');

        try {
            $this->downloaded = $this->transactionProcessor->download();
        } catch (ImporterErrorException $e) {
            Log::error('Could not download transactions from Lunch Flow.');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));

            // add error to current error thing:
            $this->addError(0, sprintf('[a109]: Could not download from GoCardless: %s', $e->getMessage()));
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw $e;
        }
    }



    private function collectTargetAccounts(): void
    {
        Log::debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->addError(0, sprintf('[a110]: Error while collecting target accounts: %s', $e->getMessage()));
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw new ImporterErrorException($e->getMessage(), 0, $e);
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
            $this->addError(0, '[a111]: No transactions were downloaded from GoCardless. You may be rate limited or something else went wrong.');
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return true;
        }

        return false;
    }


    /**
     * @throws ImporterErrorException
     */
    private function validateAccounts(): void
    {
        Log::debug('Validating accounts in configuration.');
        $accounts = $this->configuration->getAccounts();
        foreach ($accounts as $key => $accountId) {
            if (0 === (int)$accountId) {
                throw new ImporterErrorException(sprintf('Cannot import GoCardless account "%s" into Firefly III account #%d. Recreate your configuration file.', $key, $accountId));
            }
        }
    }
}
