<?php

/*
 * RoutineManager.php
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

use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Akahu\Request\AkahuDateRange;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use Illuminate\Support\Facades\Log;

final class RoutineManager implements RoutineManagerInterface
{
    use CreatesAccounts;

    private ImportJob $importJob;

    private const int CREATE_ACCOUNT_SENTINEL_ID = 0;

    public function __construct(ImportJob $importJob)
    {
        $this->importJob  = $importJob;
        $this->repository = new ImportJobRepository();
        $this->setExistingServiceAccounts($importJob->getServiceAccounts());
    }

    public function getServiceAccounts(): array
    {
        return $this->importJob->getServiceAccounts();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function start(): array
    {
        $configuration = $this->importJob->getConfiguration();
        $accounts      = $configuration->getAccounts();
        $transactions  = [];

        foreach ($accounts as $akahuAccountId => $maybeFireflyAccountId) {
            $fireflyAccountId    = $this->ensureFireflyAccountId($maybeFireflyAccountId, $akahuAccountId);
            $dateRange           = new AkahuDateRange($configuration);

            $fetcher             = new TransactionFetcher($akahuAccountId, $fireflyAccountId, $dateRange, $this->importJob);
            $converter           = new TransactionConverter($fetcher);
            $accountTransactions = $converter->convert();

            array_push($transactions, ...$accountTransactions);
        }

        return $transactions;
    }

    private function ensureFireflyAccountId(int $fireflyAccountId, string $akahuAccountId): int
    {
        if (self::CREATE_ACCOUNT_SENTINEL_ID === $fireflyAccountId) {
            Log::debug(sprintf('Creating new account for account "%s".', $akahuAccountId));

            return $this->createNewAccount($akahuAccountId);
        }

        return $fireflyAccountId;
    }
}
