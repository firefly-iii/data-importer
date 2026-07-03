<?php

/*
 * GenerateTransactions.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo\Conversion\Routine;

use App\Models\ImportJob;
use App\Services\OpenBankingIo\Model\Transaction;
use App\Support\Http\CollectsAccounts;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;

/**
 * Class GenerateTransactions
 *
 * Maps decrypted open-banking.io transactions onto Firefly III transactions.
 * A positive amount (CRDT) is a deposit, a negative amount (DBIT) a withdrawal;
 * when the counterparty IBAN matches one of the user's OTHER Firefly III asset accounts
 * the transaction is recognised as a transfer.
 */
final class GenerateTransactions
{
    use CollectsAccounts;

    /** @var array<string, int|string> open-banking.io account id => Firefly III account id */
    private array $accounts;

    /** @var array<string, int|string> IBAN => Firefly III asset account id */
    private array $targetAccounts;

    /** @var array<string, array<string, mixed>> IBAN => account info */
    private array $userAccounts;

    private ImportJob $importJob;

    public function __construct()
    {
        $this->accounts       = [];
        $this->targetAccounts = [];
        $this->userAccounts   = [];
        bcscale(12);
    }

    /**
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        Log::debug('open-banking.io: collect target accounts.');
        foreach ($this->collectAllTargetAccounts() as $iban => $info) {
            $this->targetAccounts[$iban] = $info['id'];
            $this->userAccounts[$iban]   = $info;
        }
        Log::debug(sprintf('open-banking.io: collected %d target account(s).', count($this->targetAccounts)));
    }

    /**
     * @param array<string, Transaction[]> $transactions
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTransactions(array $transactions): array
    {
        $return = [];
        foreach ($transactions as $accountId => $entries) {
            foreach ($entries as $entry) {
                $return[] = $this->generateTransaction((string) $accountId, $entry);
            }
        }
        Log::debug(sprintf('open-banking.io: parsed %d transaction(s).', count($return)));

        return $return;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateTransaction(string $accountId, Transaction $entry): array
    {
        $configuration        = $this->importJob->getConfiguration();
        $isCredit             = 1 === bccomp($entry->amount, '0');
        $fireflyId            = (int) ($this->accounts[$accountId] ?? 0);

        $transaction          = [
            'type'          => $isCredit ? 'deposit' : 'withdrawal',
            'date'          => $entry->getDate()->toW3cString(),
            'datetime'      => $entry->getDate()->toW3cString(),
            'amount'        => ltrim($entry->amount, '-'),
            'description'   => $entry->getDescription(),
            'order'         => 0,
            'currency_code' => $entry->currency,
            'external_id'   => $entry->getTransactionId(),
            'notes'         => $entry->note,
        ];

        // This account is the destination for money-in (deposit) and the source for money-out (withdrawal).
        // The counterparty is the debtor (deposit) or the creditor (withdrawal).
        $ownKey               = $isCredit ? 'destination_id' : 'source_id';
        $counterSide          = $isCredit ? 'source' : 'destination';
        $counterIban          = $isCredit ? $entry->debtorIban : $entry->creditorIban;
        $counterName          = $isCredit ? $entry->getSourceName() : $entry->getDestinationName();

        $transaction[$ownKey] = $fireflyId;
        $transaction          = $this->appendCounterparty($transaction, $counterSide, $counterName, $counterIban, $fireflyId);

        Log::debug(sprintf('[%s] Parsed open-banking.io transaction "%s" (%s %s).', config('importer.version'), $entry->getTransactionId(), $transaction['type'], $transaction['amount']));

        return ['apply_rules' => $configuration->isRules(), 'fire_webhooks' => $configuration->isWebhooks(), 'error_if_duplicate_hash' => $configuration->isIgnoreDuplicateTransactions(), 'transactions' => [$transaction]];
    }

    /**
     * Sets the counterparty side. A counterparty IBAN that resolves to one of the user's asset
     * accounts makes this a transfer -- but only when it is a DIFFERENT account than this one
     * (banks report the account's own IBAN on internal bookings, which must not become A->A).
     *
     * @param array<string, mixed> $transaction
     *
     * @return array<string, mixed>
     */
    private function appendCounterparty(array $transaction, string $side, string $name, ?string $iban, int $ownFireflyId): array
    {
        $hasIban                                = null !== $iban && '' !== $iban;
        $counterId                              = $hasIban ? (int) ($this->targetAccounts[$iban] ?? 0) : 0;

        if (0 !== $counterId && $counterId !== $ownFireflyId) {
            Log::debug(sprintf('open-banking.io: counterparty IBAN "%s" is another asset account -> transfer.', $iban));
            $transaction[sprintf('%s_id', $side)] = $counterId;
            $transaction['type']                  = 'transfer';

            return $transaction;
        }

        $transaction[sprintf('%s_name', $side)] = '' !== $name ? $name : sprintf('(unknown %s account)', $side);
        if ($hasIban) {
            $transaction[sprintf('%s_iban', $side)] = $iban;
        }

        return $transaction;
    }

    /**
     * @return array<string, int|string>
     */
    public function getTargetAccounts(): array
    {
        return $this->targetAccounts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getUserAccounts(): array
    {
        return $this->userAccounts;
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob = $importJob;
        $this->accounts  = $importJob->getConfiguration()->getAccounts();
        $this->importJob->refreshInstanceIdentifier();
    }
}
