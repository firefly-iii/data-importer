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

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CombinedProgressInformation;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Storage\StorageService;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Exception\InvalidMessageException;
use Genkgo\Camt\Reader;
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

    private string               $content;
    private TransactionConverter $transactionConverter;
    private TransactionExtractor $transactionExtractor;
    private TransactionMapper    $transactionMapper;

    public function __construct(?string $identifier)
    {
        Log::debug('Constructed CAMT RoutineManager');
        $this->content       = '';    // used in CLI
        $this->allErrors     = [];
        $this->allWarnings   = [];
        $this->allMessages   = [];
        $this->allRateLimits = [];
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

    /**
     * @throws ImporterErrorException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // make objects
        $this->transactionExtractor = new TransactionExtractor($configuration);
        $this->transactionConverter = new TransactionConverter($configuration);
        $this->transactionMapper    = new TransactionMapper($configuration);
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function start(): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // get XML file
        $camtMessage        = $this->getCamtMessage();
        if (!$camtMessage instanceof Message) {
            Log::error('The CAMT object is NULL, probably due to a previous error');
            $this->addError(0, '[a102]: The CAMT object is NULL, probably due to a previous error');
            // at this point there are very few (if not zero) errors from other steps in the routine.
            // Still: merge errors so they can be reported to the user:
            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return [];
        }
        // get raw messages
        $rawTransactions    = $this->transactionExtractor->extractTransactions($camtMessage);

        // get intermediate result (still needs processing like mapping etc)
        $pseudoTransactions = $this->transactionConverter->convert($rawTransactions);

        // put the result into firefly iii compatible arrays (and replace mapping when necessary)
        $transactions       = $this->transactionMapper->map($pseudoTransactions);

        if (0 === count($transactions)) {
            Log::error('No transactions found in CAMT file');
            $this->addError(0, '[a103]: No transactions found in CAMT file.');

            $this->mergeMessages(1);
            $this->mergeWarnings(1);
            $this->mergeErrors(1);

            return [];
        }

        $this->mergeMessages(count($transactions));
        $this->mergeWarnings(count($transactions));
        $this->mergeErrors(count($transactions));

        return $transactions;
    }

    private function getCamtMessage(): ?Message
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $camtReader  = new Reader(Config::getDefault());
        $camtMessage = null;

        try {
            // check if CLI or not and read as appropriate:
            if ('' !== $this->content) {
                // seems the CLI part
                $camtMessage = $camtReader->readString($this->content); // -> Level A
            }
            if ('' === $this->content) {
                $camtMessage = $camtReader->readString(StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE))); // -> Level A
            }
        } catch (InvalidMessageException $e) {
            Log::error('Conversion error in RoutineManager::getCamtMessage');
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $this->addError(0, sprintf('[a104]: Could not convert CAMT.053 file: %s', $e->getMessage()));

            return null;
        }

        return $camtMessage;
    }

    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays(
            [
                $this->getMessages(),
                $this->transactionConverter->getMessages(),
                $this->transactionExtractor->getMessages(),
                $this->transactionMapper->getMessages(),
            ],
            $count
        );
    }

    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays(
            [
                $this->getWarnings(),
                $this->transactionConverter->getWarnings(),
                $this->transactionExtractor->getWarnings(),
                $this->transactionMapper->getWarnings(),
            ],
            $count
        );
    }

    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays(
            [
                $this->getErrors(),
                $this->transactionConverter->getErrors(),
                $this->transactionExtractor->getErrors(),
                $this->transactionMapper->getErrors(),
            ],
            $count
        );
    }
}
