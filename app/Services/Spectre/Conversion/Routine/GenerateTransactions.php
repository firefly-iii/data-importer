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


use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Log;

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
        Log::debug('Going to collect all target accounts from Firefly III.');
        // send account list request to Firefly III.
        // TODO can only handle access token config
        $token = (string) config('importer.access_token');
        $url   = (string) config('importer.url');

        $request = new GetAccountsRequest($url, $token);
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
            Log::debug(sprintf('Collected %s (%s) under ID #%d', $iban, $entry->type, $entry->id));
            $return[$iban] = $entry->id;
            $types[$iban]  = $entry->type;
        }
        $this->targetAccounts = $return;
        $this->targetTypes    = $types;
        Log::debug(sprintf('Collected %d accounts.', count($this->targetAccounts)));
    }

    /**
     * @param array $spectre
     *
     * @return array
     * @throws ImporterErrorException
     */
    public function getTransactions(array $spectre): array
    {
        $return = [];
        foreach ($spectre as $entry) {
            $return[] = $this->generateTransaction($entry);
            // TODO error handling at this point.
        }
        $this->addMessage(0, sprintf('Parsed %d Spectre transactions for further processing.', count($return)));

        return $return;
    }

    /**
     * @param array $entry
     *
     * @return array
     * @throws ImporterErrorException
     */
    private function generateTransaction(array $entry): array
    {
        Log::debug('Original Spectre transaction', $entry);
        $description      = $entry['description'];
        $spectreAccountId = $entry['account_id'];
        // add info to the description:
        if (array_key_exists('extra', $entry) && array_key_exists('additional', $entry['extra'])) {
            $description = trim(sprintf('%s %s', $description, (string) $entry['extra']['additional']));
        }

        $return = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => !$this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [
                [
                    'type'          => 'withdrawal', // reverse
                    'date'          => str_replace('T', ' ', substr($entry['made_on'], 0, 19)),
                    'datetime'      => $entry['made_on'], // not used in API, only for transaction filtering.
                    'amount'        => 0,
                    'description'   => $description,
                    'order'         => 0,
                    'currency_code' => $entry['currency_code'],
                    'tags'          => [$entry['mode'], $entry['status'], $entry['category']],
                    'category_name' => $entry['category'],
                    'category_id'   => $this->configuration->getMapping()['categories'][$entry['category']] ?? null,
                ],
            ],
        ];
        if ($this->configuration->isIgnoreSpectreCategories()) {
            Log::debug('Remove Spectre categories + tags.');
            unset($return['transactions'][0]['tags'], $return['transactions'][0]['category_name'], $return['transactions'][0]['category_id']);
        }
        // save meta:
        $return['transactions'][0]['external_id']        = $entry['id'];
        $return['transactions'][0]['internal_reference'] = $entry['account_id'];

        if (1 === bccomp($entry['amount'], '0')) {
            Log::debug('Amount is positive: assume transfer or deposit.');
            // amount is positive: deposit or transfer. Spectre account is destination
            $return['transactions'][0]['type']   = 'deposit';
            $return['transactions'][0]['amount'] = $entry['amount'];

            // destination is Spectre
            $return['transactions'][0]['destination_id'] = (int) $this->accounts[$spectreAccountId];

            // source is the other side:
            $return['transactions'][0]['source_name'] = $entry['extra']['payee'] ?? '(unknown source account)';
        }

        if (-1 === bccomp($entry['amount'], '0')) {
            // amount is negative: withdrawal or transfer.
            Log::debug('Amount is negative: assume transfer or withdrawal.');
            $return['transactions'][0]['amount'] = bcmul($entry['amount'], '-1');

            // source is Spectre:
            $return['transactions'][0]['source_id'] = (int) $this->accounts[$spectreAccountId];
            // dest is shop
            $return['transactions'][0]['destination_name'] = $entry['extra']['payee'] ?? '(unknown destination account)';

        }
        app('log')->debug(sprintf('Parsed Spectre transaction #%d', $entry['id']));

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
}
