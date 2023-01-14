<?php

/*
 * RoutineManager.php
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

namespace App\Services\Spectre\Conversion;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Spectre\Conversion\Routine\FilterTransactions;
use App\Services\Spectre\Conversion\Routine\GenerateTransactions;
use App\Services\Spectre\Conversion\Routine\TransactionProcessor;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use GeneratesIdentifier;

    private array $allMessages;
    private array $allWarnings;
    private array $allErrors;

    private Configuration        $configuration;
    private TransactionProcessor $transactionProcessor;
    private GenerateTransactions $transactionGenerator;
    private FilterTransactions   $transactionFilter;

    /**
     *
     */
    public function __construct(?string $identifier)
    {
        // TODO conversion does not add errors, warnings and messages.
        $this->allErrors   = [];
        $this->allWarnings = [];
        $this->allMessages = [];

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

    /**
     * @inheritDoc
     */
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
     * @inheritDoc
     */
    public function start(): array
    {
        // get transactions from Spectre
        $transactions = $this->transactionProcessor->download();

        // generate Firefly III ready transactions:
        app('log')->debug('Generating Firefly III transactions.');
        $this->transactionGenerator->collectTargetAccounts();

        $converted = $this->transactionGenerator->getTransactions($transactions);
        app('log')->debug(sprintf('Generated %d Firefly III transactions.', count($converted)));

        $filtered = $this->transactionFilter->filter($converted);
        app('log')->debug(sprintf('Filtered down to %d Firefly III transactions.', count($filtered)));

        return $filtered;
    }


    /**
     * @return array
     */
    public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    /**
     * @return array
     */
    public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    /**
     * @return array
     */
    public function getAllErrors(): array
    {
        return $this->allErrors;
    }
}
