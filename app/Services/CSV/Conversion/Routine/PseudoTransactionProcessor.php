<?php

/*
 * PseudoTransactionProcessor.php
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

namespace App\Services\CSV\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\CSV\Conversion\Task\AbstractTask;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Import\Routine\GetsDefaultAccount;
use App\Services\Shared\Import\Routine\GetsPrimaryCurrency;
use App\Support\RequestCache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetCurrencyRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use GrumpyDictator\FFIIIApiSupport\Response\GetCurrencyResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class PseudoTransactionProcessor
 */
final class PseudoTransactionProcessor
{
    use GetsDefaultAccount;
    use GetsPrimaryCurrency;
    private ImportJob $importJob;
    private array $tasks;

    /**
     * PseudoTransactionProcessor constructor.
     *
     * @throws ImporterErrorException
     */
    public function __construct(ImportJob $importJob)
    {
        $this->importJob  = $importJob;
        $defaultAccountId = $importJob->getConfiguration()->getDefaultAccount();
        $this->tasks      = config('csv.transaction_tasks');
        $this->getDefaultAccount($defaultAccountId);
        $this->getPrimaryCurrency();
    }





    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
    }

    public function processPseudo(array $lines): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $count     = count($lines);
        $processed = [];
        Log::info(sprintf('Converting %d line(s) into transactions.', $count));

        /** @var array $line */
        foreach ($lines as $index => $line) {
            Log::info(sprintf('Now processing line %d/%d.', $index + 1, $count));
            $processed[] = $this->processPseudoLine($line);

            // $this->addMessage($index, sprintf('Converted CSV line %d into a transaction.', $index + 1));
        }
        Log::info(sprintf('Done converting %d line(s) into transactions.', $count));

        return $processed;
    }

    private function processPseudoLine(array $line): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__), $line);
        foreach ($this->tasks as $task) {
            /** @var AbstractTask $object */
            $object = app($task);
            Log::debug(sprintf('Now running task %s', $task));
            if ($object->requiresDefaultAccount()) {
                $object->setAccount($this->defaultAccount);
            }
            if ($object->requiresTransactionCurrency()) {
                $object->setTransactionCurrency($this->primaryCurrency);
            }

            $line   = $object->process($line);
        }
        Log::debug('Final transaction: ', $line);

        return $line;
    }
}
