<?php

/*
 * GenerateTransactions.php
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

namespace App\Services\EnableBanking\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\EnableBanking\Model\Transaction;
use App\Services\Shared\Configuration\Configuration;
use App\Support\Http\CollectsAccounts;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;

/**
 * Class GenerateTransactions
 */
class GenerateTransactions
{
    use CollectsAccounts;

    private array $accounts;
    private Configuration $configuration;
    private ImportJob $importJob;
    private array $targetAccounts;
    private array $targetTypes;
    private array $userAccounts;

    public function __construct()
    {
        $this->targetAccounts = [];
        $this->targetTypes = [];
        $this->userAccounts = [];
        bcscale(12);
    }

    /**
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        Log::debug('Enable Banking: Defer account search to trait.');

        $array = $this->collectAllTargetAccounts();
        foreach ($array as $number => $info) {
            $this->targetAccounts[$number] = $info['id'];
            $this->targetTypes[$number] = $info['type'];
            $this->userAccounts[$number] = $info;
        }

        Log::debug(sprintf('Enable Banking: Collected %d target accounts.', count($this->targetAccounts)));
    }

    public function getTransactions(array $transactions): array
    {
        Log::debug('Now generate transactions.');
        $return = [];

        foreach ($transactions as $accountUid => $entries) {
            $total = count($entries);
            Log::debug(sprintf('Going to parse account %s with %d transaction(s).', $accountUid, $total));

            foreach ($entries as $index => $entry) {
                Log::debug(sprintf('[%s] [%d/%d] Parsing transaction', config('importer.version'), $index + 1, $total));
                $return[] = $this->generateTransaction($accountUid, $entry);
                Log::debug(sprintf('[%s] [%d/%d] Done parsing transaction.', config('importer.version'), $index + 1, $total));
            }
        }

        Log::debug('Done parsing transactions.');

        return $return;
    }

    private function generateTransaction(string $accountUid, Transaction $entry): array
    {
        Log::debug(sprintf('Enable Banking transaction: "%s" with amount %s %s', $entry->getDescription(), $entry->currencyCode, $entry->transactionAmount));

        $return = [
            'apply_rules' => $this->configuration->isRules(),
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions' => [],
        ];

        $valueDate = $entry->getValueDate();
        $transaction = [
            'type' => 'withdrawal',
            'date' => $entry->getDate()->toW3cString(),
            'datetime' => $entry->getDate()->toW3cString(),
            'amount' => $entry->transactionAmount,
            'description' => $entry->getCleanDescription(),
            'payment_date' => $valueDate instanceof Carbon ? $valueDate->format('Y-m-d') : '',
            'order' => 0,
            'currency_code' => $entry->currencyCode,
            'tags' => $entry->tags,
            'category_name' => null,
            'category_id' => null,
            'notes' => $entry->getNotes(),
            'external_id' => $entry->getTransactionId(),
            'internal_reference' => $entry->accountUid,
        ];

        if (1 === bccomp($entry->transactionAmount, '0')) {
            Log::debug('Amount is positive: assume transfer or deposit.');
            $transaction = $this->appendPositiveAmountInfo($accountUid, $transaction, $entry);
        }

        if (-1 === bccomp($entry->transactionAmount, '0')) {
            Log::debug('Amount is negative: assume transfer or withdrawal.');
            $transaction = $this->appendNegativeAmountInfo($accountUid, $transaction, $entry);
        }

        $return['transactions'][] = $transaction;
        Log::debug(sprintf('[%s] Parsed Enable Banking transaction "%s".', config('importer.version'), $entry->getTransactionId()));

        return $return;
    }

    private function appendPositiveAmountInfo(string $accountUid, array $transaction, Transaction $entry): array
    {
        $transaction['type'] = 'deposit';
        $transaction['amount'] = $entry->transactionAmount;
        $transaction['destination_id'] = (int) $this->accounts[$accountUid];

        // Set source info
        $sourceName = $entry->getSourceName();
        $sourceIban = $entry->getSourceIban();

        if (null !== $sourceIban && '' !== $sourceIban) {
            $transaction['source_iban'] = $sourceIban;

            // Check if IBAN is a known asset account
            if (array_key_exists($sourceIban, $this->targetAccounts)) {
                $transaction['source_id'] = $this->targetAccounts[$sourceIban];
                $transaction['type'] = 'transfer';
            }
        }

        if (null !== $sourceName) {
            $transaction['source_name'] = $sourceName;
        } elseif (!isset($transaction['source_id'])) {
            $transaction['source_name'] = '(unknown source)';
        }

        return $transaction;
    }

    private function appendNegativeAmountInfo(string $accountUid, array $transaction, Transaction $entry): array
    {
        $transaction['amount'] = bcmul($entry->transactionAmount, '-1');
        $transaction['source_id'] = (int) $this->accounts[$accountUid];

        // Set destination info
        $destName = $entry->getDestinationName();
        $destIban = $entry->getDestinationIban();

        if (null !== $destIban && '' !== $destIban) {
            $transaction['destination_iban'] = $destIban;

            // Check if IBAN is a known asset account
            if (array_key_exists($destIban, $this->targetAccounts)) {
                $transaction['destination_id'] = $this->targetAccounts[$destIban];
                $transaction['type'] = 'transfer';
            }
        }

        if (null !== $destName) {
            $transaction['destination_name'] = $destName;
        } elseif (!isset($transaction['destination_id'])) {
            $transaction['destination_name'] = '(unknown destination)';
        }

        return $transaction;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob = $importJob;
        $this->configuration = $importJob->getConfiguration();
        $this->accounts = $this->configuration->getAccounts();
    }

    public function getUserAccounts(): array
    {
        return $this->userAccounts;
    }
}
