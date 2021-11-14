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

namespace App\Services\Nordigen\Conversion;

use App\Services\CSV\Configuration\Configuration;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Log;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use IsRunningCli, GeneratesIdentifier;

    private Configuration        $configuration;
    private TransactionProcessor $transactionProcessor;

    /**
     *
     */
    public function __construct(?string $identifier)
    {
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
        $this->transactionProcessor = new TransactionProcessor;
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

        // set identifier
        $this->transactionProcessor->setIdentifier($this->identifier);

    }

    /**
     * @inheritDoc
     */
    public function start(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        // get transactions from Nordigen
        Log::debug('Call transaction processor download.');
        $nordigen = $this->transactionProcessor->download();

        echo '<pre>';
        var_dump($nordigen);
        exit;
        die('need to convert to Firefly III things, then return.');

    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
