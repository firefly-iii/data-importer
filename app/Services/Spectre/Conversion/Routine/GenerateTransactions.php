<?php
/**
 * GenerateTransactions.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III Spectre importer
 * (https://github.com/firefly-iii/spectre-importer).
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

namespace App\Services\Spectre\Conversion\Routine;


use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Services\Spectre\Model\Transaction;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use ProgressInformation;

    private array         $accounts;
    private Configuration $configuration;
    private array         $specialSubTypes = ['REVERSAL', 'REQUEST', 'BILLING', 'SCT', 'SDD', 'NLO'];
    private array         $targetAccounts;
    private array         $targetTypes;

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts = [];
        $this->targetTypes    = [];
        bcscale(14);
    }

    /**
     *
     */
    public function collectTargetAccounts(): void
    {
        app('log')->debug('Going to collect all target accounts from Firefly III.');
        // send account list request to Firefly III.
        $token   = SecretManager::getAccessToken();
        $url     = SecretManager::getBaseUrl();
        $request = new GetAccountsRequest($url, $token);

        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        /** @var GetAccountsResponse $result */
        $result = $request->get();
        $return = [];
        $types  = [];
        /** @var Account $entry */
        foreach ($result as $entry) {
            $type = $entry->type;
            if (in_array($type, ['reconciliation', 'initial-balance', 'expense', 'revenue'], true)) {
                continue;
            }
            $iban = $entry->iban;
            if ('' === (string) $iban) {
                continue;
            }
            app('log')->debug(sprintf('Collected %s (%s) under ID #%d', $iban, $entry->type, $entry->id));
            $return[$iban] = $entry->id;
            $types[$iban]  = $entry->type;
        }
        $this->targetAccounts = $return;
        $this->targetTypes    = $types;
        app('log')->debug(sprintf('Collected %d accounts.', count($this->targetAccounts)));
    }

    /**
     * @param array $spectre
     *
     * @return array
     */
    public function getTransactions(array $spectre): array
    {
        $return = [];
        /** @var Transaction $entry */
        foreach ($spectre as $entry) {
            $return[] = $this->generateTransaction($entry);
            // TODO error handling at this point.
        }
        //$this->addMessage(0, sprintf('Parsed %d Spectre transactions for further processing.', count($return)));

        return $return;
    }

    /**
     * @param Transaction $entry
     *
     * @return array
     */
    private function generateTransaction(Transaction $entry): array
    {
        app('log')->debug('Original Spectre transaction', $entry->toArray());
        $description      = $entry->getDescription();
        $spectreAccountId = $entry->getAccountId();
        $madeOn           = $entry->getMadeOn()->toW3cString();
        $amount           = $entry->getAmount();

        // extra information from the "extra" array. May be NULL.
        $notes = trim(sprintf('%s %s',$entry->extra->getInformation(), $entry->extra->getAdditional()));

        $transaction = [
            'type'              => 'withdrawal', // reverse
            'date'              => str_replace('T', ' ', substr($madeOn, 0, 19)),
            'datetime'          => $madeOn, // not used in API, only for transaction filtering.
            'amount'            => 0,
            'description'       => $description,
            'order'             => 0,
            'currency_code'     => $entry->getCurrencyCode(),
            'tags'              => [$entry->getMode(), $entry->getStatus(), $entry->getCategory()],
            'category_name'     => $entry->getCategory(),
            'category_id'       => $this->configuration->getMapping()['categories'][$entry->getCategory()] ?? null,
            'external_id'       => $entry->getId(),
            'interal_reference' => $entry->getAccountId(),
            'notes'             => $notes,
        ];

        $return = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];

        if ($this->configuration->isIgnoreSpectreCategories()) {
            app('log')->debug('Remove Spectre categories and tags.');
            unset($transaction['tags']);
            unset($transaction['category_name']);
            unset($transaction['category_id']);
        }

        // amount is positive?
        if (1 === bccomp($amount, '0')) {
            app('log')->debug('Amount is positive: assume transfer or deposit.');
            $transaction = $this->processPositiveTransaction($entry, $transaction, $amount, $spectreAccountId);
        }

        if (-1 === bccomp($amount, '0')) {
            app('log')->debug('Amount is negative: assume transfer or withdrawal.');
            $transaction = $this->processNegativeTransaction($entry, $transaction, $amount, $spectreAccountId);
        }

        $return['transactions'][] = $transaction;

        app('log')->debug(sprintf('Parsed Spectre transaction #%d', $entry->getId()));

        return $return;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
    }

    /**
     * @param Transaction $entry
     * @param array       $transaction
     * @param string      $amount
     * @param string      $spectreAccountId
     * @return array
     */
    private function processPositiveTransaction(Transaction $entry, array $transaction, string $amount, string $spectreAccountId): array
    {

        // amount is positive: deposit or transfer. Spectre account is destination
        $transaction['type']   = 'deposit';
        $transaction['amount'] = $amount;

        // destination is Spectre
        $transaction['destination_id'] = (int) $this->accounts[$spectreAccountId];

        // source is the other side (name!)
        $transaction['source_name'] = $entry->getPayee('source');

        app('log')->debug(sprintf('source_name = "%s", destination_id = %d', $transaction['source_name'], $transaction['destination_id']));

        return $transaction;
    }

    /**
     * @param Transaction $entry
     * @param array       $transaction
     * @param string      $amount
     * @param string      $spectreAccountId
     * @return array
     */
    private function processNegativeTransaction(Transaction $entry, array $transaction, string $amount, string $spectreAccountId): array
    {
        // amount is negative: withdrawal or transfer.
        $transaction['amount'] = bcmul($amount, '-1');

        // source is Spectre:
        $transaction['source_id'] = (int) $this->accounts[$spectreAccountId];
        // dest is shop
        $transaction['destination_name'] = $entry->getPayee('destination');

        app('log')->debug(sprintf('source_id = %d, destination_name = "%s"', $transaction['source_id'], $transaction['destination_name']));
        return $transaction;
    }
}
