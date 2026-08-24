<?php

/*
 * TransactionFetcher.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Akahu\Conversion;

use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Services\Akahu\Model\Account\Account;
use App\Services\Akahu\Request\AkahuDateRange;
use App\Services\Akahu\Request\GetTransactionsRequest;
use Illuminate\Support\Facades\Log;

final class TransactionFetcher
{
    private string $akahuAccountId;
    private int $fireflyAccountId;
    // private ?Carbon $startDate;
    // private ?Carbon $endDate;
    private ImportJob $importJob;
    private AkahuDateRange $dateRange;

    public function __construct(string $akahuAccountId, int $fireflyAccountId, AkahuDateRange $dateRange, ImportJob $importJob)
    {
        $this->akahuAccountId   = $akahuAccountId;
        $this->fireflyAccountId = $fireflyAccountId;
        $this->dateRange        = $dateRange;
        $this->importJob        = $importJob;
    }

    public function fetch(): array
    {
        $request        = new GetTransactionsRequest($this->akahuAccountId, $this->dateRange);

        try {
            $response = $request->get();
        } catch (ImporterHttpException $e) {
            $msg = sprintf('Failed get transactions from the Akahu api: %s', $e->getMessage());

            Log::error($msg);
            $this->importJob->conversionStatus->addError(0, e($msg));

            return [];
        }

        $transactions   = $response->getTransactions();
        $akahuAccountId = $this->akahuAccountId;
        $account        = array_find($this->importJob->getServiceAccounts(), function (Account $account) use ($akahuAccountId) {
            return $akahuAccountId === $account->getAkahuId();
        });

        $msg            = sprintf('Successfully fetched %d transactions for Akahu account "%s"', count($transactions), $account->getName());

        $this->importJob->conversionStatus->addMessage(0, e($msg));

        return $transactions;
    }

    public function getAkahuAccountId(): string
    {
        return $this->akahuAccountId;
    }

    public function getFireflyAccountId(): int
    {
        return $this->fireflyAccountId;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }
}
