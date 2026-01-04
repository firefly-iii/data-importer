<?php
/*
 * TransactionConverter.php
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

namespace App\Services\Sophtron\Conversion;

use App\Models\ImportJob;
use App\Services\Sophtron\Model\Transaction;
use App\Support\Facades\Steam;
use Illuminate\Support\Facades\Log;

class TransactionConverter
{

    private ImportJob $importJob;
    private bool      $isRules        = true;
    private bool      $errorIfHash    = true;
    private int       $defaultAccount = 0;

    public function __construct(ImportJob $importJob)
    {
        $importJob->refreshInstanceIdentifier();
        $this->importJob      = $importJob;
        $this->isRules        = $this->importJob->getConfiguration()->isRules();
        $this->errorIfHash    = $this->importJob->getConfiguration()->isIgnoreDuplicateTransactions();
        $this->defaultAccount = $this->importJob->getConfiguration()->getDefaultAccount();
    }

    public function convert(array $transactions): array
    {
        $result = [];
        foreach ($transactions as $transaction) {
            $original = Transaction::fromArray($transaction);
            $result[] = $this->convertTransaction($original);
        }
        return $result;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    private function convertTransaction(Transaction $original): array
    {
        $return = [
            'apply_rules'             => $this->isRules,
            'error_if_duplicate_hash' => $this->errorIfHash,
            'transactions'            => [],
        ];
        $split  = [
            'type'          => 'withdrawal',
            'date'          => null === $original->date ? now()->toW3cString() : $original->date->toW3cString(),
            'amount'        => Steam::positive($original->amount),
            'description'   => '' === $original->description ? '(no description)' : $original->description,
            'order'         => 0,
            'category_name' => $original->category,
            'external_id'   => $original->id,
            'notes'         => $original->memo,
            'external_url'  => $original->checkImage,
            // 'bonus_tags'    => [],
            //      "source_id": "2",
            //      "source_name": "Checking account",
            //      "destination_id": "2",
            //      "destination_name": "Buy and Large",

            "book_date"    => null === $original->postDate ? '' : $original->postDate->toW3cString(),
            "process_date" => null === $original->transactionDate ? '' : $original->transactionDate->toW3cString(),
        ];

        if (1 === bccomp($original->amount, '0', 2)) {
            Log::debug('Amount is positive: assume transfer or deposit.');
            $split = $this->appendPositiveAmountInfo($split, $original);
        }

        if (-1 === bccomp($original->amount, '0', 2)) {
            Log::debug('Amount is negative: assume transfer or withdrawal.');
            $split = $this->appendNegativeAmountInfo($split, $original);
        }
        $return['transactions'][] = $split;
        Log::debug(sprintf('[%s] Parsed Sophtron transaction "%s".', config('importer.version'), $original->id), $split);
        return $return;
    }

    private function appendNegativeAmountInfo(array $split, Transaction $original): array
    {
        $accountId                 = $original->userInstitutionAccountId;
        $appAccountId              = $this->importJob->getConfiguration()->getAccounts()[$accountId] ?? $this->defaultAccount;
        $split['source_id']        = $appAccountId;
        $split['destination_name'] = '' === $original->merchant ? '(unknown expense account)' : $original->merchant;
        return $split;
    }

    private function appendPositiveAmountInfo(array $split, Transaction $original): array
    {
        $accountId               = $original->userInstitutionAccountId;
        $appAccountId            = $this->importJob->getConfiguration()->getAccounts()[$accountId] ?? $this->defaultAccount;
        $split['type']           = 'deposit';
        $split['source_name']    = '' === $original->merchant ? '(unknown revenue account)' : $original->merchant;
        $split['destination_id'] = $appAccountId;
        return $split;
    }

}
