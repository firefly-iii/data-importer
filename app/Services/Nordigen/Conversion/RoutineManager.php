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


namespace App\Services\Nordigen\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Nordigen\Conversion\Routine\FilterTransactions;
use App\Services\Nordigen\Conversion\Routine\GenerateTransactions;
use App\Services\Nordigen\Conversion\Routine\TransactionProcessor;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Log;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    private array                      $allMessages;
    private array                      $allWarnings;
    private array                      $allErrors;
    use IsRunningCli, GeneratesIdentifier;

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

        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
        $this->transactionProcessor = new TransactionProcessor;
        $this->transactionGenerator = new GenerateTransactions;
        $this->transactionFilter    = new FilterTransactions;
    }

    /**
     * @inheritDoc
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;

        // share config
        $this->transactionProcessor->setConfiguration($configuration);
        $this->transactionGenerator->setConfiguration($configuration);
        //$this->transactionFilter->setConfiguration($configuration);

        // set identifier
        $this->transactionProcessor->setIdentifier($this->identifier);
        $this->transactionGenerator->setIdentifier($this->identifier);
        $this->transactionFilter->setIdentifier($this->identifier);

    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // get transactions from Nordigen
        Log::debug('Call transaction processor download.');
        $nordigen = $this->transactionProcessor->download();

        // generate Firefly III ready transactions:
        app('log')->debug('Generating Firefly III transactions.');
        $this->transactionGenerator->collectTargetAccounts();

        try {
            $this->transactionGenerator->collectNordigenAccounts();
        } catch (ImporterErrorException $e) {
            Log::error('Could not collect info on all Nordigen accounts, but this info isnt used at the moment anyway.');
            Log::error($e->getMessage());
        }

        $transactions = $this->transactionGenerator->getTransactions($nordigen);
        app('log')->debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        $filtered = $this->transactionFilter->filter($transactions);
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
