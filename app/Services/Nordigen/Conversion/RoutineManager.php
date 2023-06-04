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
use App\Services\Nordigen\Conversion\Routine\FilterTransactions;
use App\Services\Nordigen\Conversion\Routine\GenerateTransactions;
use App\Services\Nordigen\Conversion\Routine\TransactionProcessor;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use IsRunningCli;
    use GeneratesIdentifier;

    private array                $allErrors;
    private array                $allMessages;
    private array                $allWarnings;
    private Configuration        $configuration;
    private FilterTransactions   $transactionFilter;
    private GenerateTransactions $transactionGenerator;
    private TransactionProcessor $transactionProcessor;

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
        $this->transactionProcessor = new TransactionProcessor();
        $this->transactionGenerator = new GenerateTransactions();
        $this->transactionFilter    = new FilterTransactions();
    }

    /**
     * @return array
     */
    public function getAllErrors(): array
    {
        return $this->allErrors;
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
        app('log')->debug(sprintf('Now in %s', __METHOD__));

        // get transactions from Nordigen
        app('log')->debug('Call transaction processor download.');
        $nordigen = $this->transactionProcessor->download();

        // collect errors from transactionProcessor.
        $this->mergeMessages($this->transactionProcessor->getMessages());
        $this->mergeWarnings($this->transactionProcessor->getWarnings());
        $this->mergeErrors($this->transactionProcessor->getErrors());

        if (0 === count($nordigen)) {
            app('log')->warning('Downloaded nothing, will return nothing.');

            return $nordigen;
        }
        // generate Firefly III ready transactions:
        app('log')->debug('Generating Firefly III transactions.');
        $this->transactionGenerator->collectTargetAccounts();

        try {
            $this->transactionGenerator->collectNordigenAccounts();
        } catch (ImporterErrorException $e) {
            app('log')->error('Could not collect info on all Nordigen accounts, but this info isn\'t used at the moment anyway.');
            app('log')->error($e->getMessage());
        }

        $transactions = $this->transactionGenerator->getTransactions($nordigen);
        app('log')->debug(sprintf('Generated %d Firefly III transactions.', count($transactions)));

        // collect errors from transaction generator
        $this->mergeMessages($this->transactionGenerator->getMessages());
        $this->mergeWarnings($this->transactionGenerator->getWarnings());
        $this->mergeErrors($this->transactionGenerator->getErrors());

        $filtered = $this->transactionFilter->filter($transactions);
        app('log')->debug(sprintf('Filtered down to %d Firefly III transactions.', count($filtered)));

        // collect errors from transaction filter
        $this->mergeMessages($this->transactionFilter->getMessages());
        $this->mergeWarnings($this->transactionFilter->getWarnings());
        $this->mergeErrors($this->transactionFilter->getErrors());

        return $filtered;
    }

    /**
     * @param  array  $errors
     *
     * @return void
     */
    private function mergeErrors(array $errors): void
    {
        foreach ($errors as $index => $array) {
            $exists = array_key_exists($index, $this->allErrors);
            if (true === $exists) {
                $this->allErrors[$index] = array_merge($this->allErrors[$index], $array);
            }
            if (false === $exists) {
                $this->allErrors[$index] = $array;
            }
        }
    }

    /**
     * @param  array  $messages
     *
     * @return void
     */
    private function mergeMessages(array $messages): void
    {
        foreach ($messages as $index => $array) {
            $exists = array_key_exists($index, $this->allMessages);
            if (true === $exists) {
                $this->allMessages[$index] = array_merge($this->allMessages[$index], $array);
            }
            if (false === $exists) {
                $this->allMessages[$index] = $array;
            }
        }
    }

    /**
     * @param  array  $warnings
     *
     * @return void
     */
    private function mergeWarnings(array $warnings): void
    {
        foreach ($warnings as $index => $array) {
            $exists = array_key_exists($index, $this->allWarnings);
            if (true === $exists) {
                $this->allWarnings[$index] = array_merge($this->allWarnings[$index], $array);
            }
            if (false === $exists) {
                $this->allWarnings[$index] = $array;
            }
        }
    }
}
