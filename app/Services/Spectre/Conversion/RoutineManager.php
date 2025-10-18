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

namespace App\Services\Spectre\Conversion;

use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Spectre\Conversion\Routine\FilterTransactions;
use App\Services\Spectre\Conversion\Routine\GenerateTransactions;
use App\Services\Spectre\Conversion\Routine\TransactionProcessor;
use App\Support\Http\CollectsAccounts;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use Override;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use CollectsAccounts;
    use CombinedProgressInformation;
    use GeneratesIdentifier;
    use ProgressInformation;

    private array                $allErrors;
    private array                $allMessages;
    private array                $allWarnings;
    private Configuration        $configuration;
    private FilterTransactions   $transactionFilter;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;

    public function __construct(?string $identifier)
    {
        $this->allErrors            = [];
        $this->allWarnings          = [];
        $this->allMessages          = [];
        $this->allRateLimits        = [];

        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->transactionFilter    = new FilterTransactions();
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
    }

    #[Override]
    public function getServiceAccounts(): array
    {
        return [];
    }

    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;
        $this->transactionProcessor->setConfiguration($configuration);
        $this->transactionProcessor->setDownloadIdentifier($this->getIdentifier());
        $this->transactionGenerator->setConfiguration($configuration);
        $this->transactionGenerator->setIdentifier($this->getIdentifier());
        $this->transactionFilter->setIdentifier($this->getIdentifier());
    }

    /**
     * @throws ImporterHttpException
     */
    public function start(): array
    {
        // get transactions from Spectre
        $transactions = $this->transactionProcessor->download();

        // generate Firefly III ready transactions:
        Log::debug('Generating Firefly III transactions.');

        try {
            $this->transactionGenerator->collectTargetAccounts();
        } catch (ApiHttpException $e) {
            $this->addError(0, sprintf('[a122]: Cannot download Spectre accounts: %s', $e->getMessage()));
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            throw new ImporterHttpException($e->getMessage(), 0, $e);
        }

        $converted    = $this->transactionGenerator->getTransactions($transactions);
        Log::debug(sprintf('Generated %d Firefly III transactions.', count($converted)));
        if (0 === count($converted)) {
            $this->addError(0, '[a123]: No transactions were converted, probably zero found at Spectre.');
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return [];
        }

        $filtered     = $this->transactionFilter->filter($converted);
        Log::debug(sprintf('Filtered down to %d Firefly III transactions.', count($filtered)));

        $this->mergeMessages(count($transactions));
        $this->mergeWarnings(count($transactions));
        $this->mergeErrors(count($transactions));

        return $filtered;
    }

    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays(
            [
                $this->getMessages(),
                $this->transactionFilter->getMessages(),
                $this->transactionProcessor->getMessages(),
                $this->transactionGenerator->getMessages(),
            ],
            $count
        );
    }

    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays(
            [
                $this->getWarnings(),
                $this->transactionFilter->getWarnings(),
                $this->transactionProcessor->getWarnings(),
                $this->transactionGenerator->getWarnings(),
            ],
            $count
        );
    }

    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays(
            [
                $this->getErrors(),
                $this->transactionFilter->getErrors(),
                $this->transactionProcessor->getErrors(),
                $this->transactionGenerator->getErrors(),
            ],
            $count
        );
    }
}
