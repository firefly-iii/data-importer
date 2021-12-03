<?php
/*
 * GenerateTransactions.php
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

namespace App\Services\Nordigen\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Nordigen\Model\Transaction;
use App\Services\Nordigen\Request\GetAccountInformationRequest;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Conversion\ProgressInformation;
use Cache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
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
    private array         $targetAccounts;
    private array         $targetTypes;
    private array         $nordigenAccountInfo;

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts      = [];
        $this->targetTypes         = [];
        $this->nordigenAccountInfo = [];
        bcscale(16);
    }

    /**
     *
     */
    public function collectTargetAccounts(): void
    {
        if (config('importer.use_cache') && Cache::has('collect_target_accounts')) {
            Log::debug('Grab target accounts from cache.');
            $info                 = Cache::get('collect_target_accounts');
            $this->targetAccounts = $info['accounts'];
            $this->targetTypes    = $info['types'];
            return;
        }
        Log::debug('Going to collect all target accounts from Firefly III.');
        // send account list request to Firefly III.
        $token   = (string) config('importer.access_token');
        $url     = (string) config('importer.url');
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
            Log::debug(sprintf('Added account #%d (%s) with IBAN "%s"', $entry->id, $entry->type, $iban));
        }
        $this->targetAccounts = $return;
        $this->targetTypes    = $types;
        Log::debug(sprintf('Collected %d accounts.', count($this->targetAccounts)));
        if (config('importer.use_cache')) {
            $array = [
                'accounts' => $return,
                'types'    => $types,
            ];
            Cache::put('collect_target_accounts', $array, 86400); // 24h
            Log::info('Stored collected accounts in cache.');
        }
    }

    /**
     * TODO the result of this method is currently not used.
     *
     * @throws ImporterErrorException
     */
    public function collectNordigenAccounts(): void
    {
        if (config('importer.use_cache') && Cache::has('collect_nordigen_accounts')) {
            Log::debug('Grab Nordigen accounts from cache.');
            $this->nordigenAccountInfo = Cache::get('collect_nordigen_accounts');
            return;
        }
        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $info        = [];
        Log::debug('Going to collect account information from Nordigen.');
        /**
         * @var string $nordigenIdentifier
         * @var int    $account
         */
        foreach ($this->accounts as $nordigenIdentifier => $account) {
            Log::debug(sprintf('Now at #%d => %s', $account, $nordigenIdentifier));
            $set = [];
            // get account details
            $request = new GetAccountInformationRequest($url, $accessToken, $nordigenIdentifier);
            /** @var ArrayResponse $response */
            try {
                $response = $request->get();
            } catch (ImporterHttpException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
            $accountInfo               = $response->data['account'];
            $set['iban']               = $accountInfo['iban'] ?? '';
            $info[$nordigenIdentifier] = $set;
            Log::debug(sprintf('Collected IBAN "%s" for Nordigen account "%s"', $set['iban'], $nordigenIdentifier));
        }
        $this->nordigenAccountInfo = $info;
        if (config('importer.use_cache')) {
            Cache::put('collect_nordigen_accounts', $info, 86400); // 24h
            Log::info('Stored collected Nordigen accounts in cache.');
        }
    }

    /**
     * @param array $transactions
     *
     * @return array
     */
    public function getTransactions(array $transactions): array
    {
        Log::debug('Now generate transactions.');
        $return = [];
        /**
         * @var string $accountId
         * @var array  $entries
         */
        foreach ($transactions as $accountId => $entries) {
            $total = count($entries);
            app('log')->debug(sprintf('Going to parse account %s with %d transaction(s).', $accountId, $total));
            /**
             * @var int         $index
             * @var Transaction $entry
             */
            foreach ($entries as $index => $entry) {
                Log::debug(sprintf('[%d/%d] Parsing transaction.', ($index + 1), $total));
                $return[] = $this->generateTransaction($accountId, $entry);
                Log::debug(sprintf('[%d/%d] Done parsing transaction.', ($index + 1), $total));
            }
        }
        $this->addMessage(0, sprintf('Parsed %d Spectre transactions for further processing.', count($return)));
        Log::debug('Done parsing transactions.');
        return $return;
    }

    /**
     * TODO function is way too complex.
     *
     * @param string      $accountId
     * @param Transaction $entry
     * @return array
     */
    private function generateTransaction(string $accountId, Transaction $entry): array
    {
        Log::debug(sprintf('Nordigen transaction: "%s" with amount %s %s', $entry->getDescription(), $entry->currencyCode, $entry->transactionAmount));

        $return = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => !$this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [
                [
                    'type'          => 'withdrawal', // reverse
                    'date'          => $entry->getDate()->format('Y-m-d'),
                    'datetime'      => $entry->getDate()->toW3cString(),
                    'amount'        => $entry->transactionAmount,
                    'description'   => $entry->getDescription(),
                    'order'         => 0,
                    'currency_code' => $entry->currencyCode,
                    'tags'          => [],
                    'category_name' => null,
                    'category_id'   => null,
                ],
            ],
        ];

        // save meta:
        $return['transactions'][0]['external_id']        = $entry->transactionId;
        $return['transactions'][0]['internal_reference'] = $entry->accountIdentifier;

        if (1 === bccomp($entry->transactionAmount, '0')) {
            Log::debug('Amount is positive: perhaps transfer or deposit.');
            // amount is positive: deposit or transfer. Spectre account is destination
            $return['transactions'][0]['type']   = 'deposit';
            $return['transactions'][0]['amount'] = $entry->transactionAmount;

            // destination is a Nordigen account
            $return['transactions'][0]['destination_id'] = (int) $this->accounts[$accountId];

            // source iban valid?
            $sourceIban = $entry->getSourceIban() ?? '';
            if ('' !== $sourceIban && array_key_exists($sourceIban, $this->targetAccounts)) {
                // source is also an ID:
                Log::debug(sprintf('Recognized %s as a Firefly III asset account so this is a transfer.', $sourceIban));
                $return['transactions'][0]['source_id'] = $this->targetAccounts[$sourceIban];
                $return['transactions'][0]['type']      = 'transfer';
            }

            if ('' === $sourceIban || !array_key_exists($sourceIban, $this->targetAccounts)) {
                Log::debug(sprintf('"%s" is not a valid IBAN OR not recognized as Firefly III asset account so submitted as-is.', $sourceIban));
                // source is the other side:
                $return['transactions'][0]['source_name'] = $entry->getSourceName() ?? '(unknown source account)';
                $return['transactions'][0]['source_iban'] = $entry->getSourceIban() ?? null;
            }

            $mappedId = null;
            if (isset($return['transactions'][0]['source_name'])) {
                Log::debug(sprintf('Check if "%s" is mapped to an account by the user.', $return['transactions'][0]['source_name']));
                $mappedId = $this->getMappedAccountId($return['transactions'][0]['source_name']);
            }

            if (null !== $mappedId && 0 !== $mappedId) {
                Log::debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $return['transactions'][0]['source_name'], $mappedId));
                $mappedType                             = $this->getMappedAccountType($mappedId);
                $originalSourceName                     = $return['transactions'][0]['source_name'];
                $return['transactions'][0]['source_id'] = $mappedId;
                // catch error here:
                try {
                    $return['transactions'][0]['type'] = $this->getTransactionType($mappedType, 'asset');
                    Log::debug(sprintf('Transaction type seems to be %s', $return['transactions'][0]['type']));
                } catch (ImporterErrorException $e) {
                    Log::error($e->getMessage());
                    Log::info('Will not use mapped ID, Firefly III account is of the wrong type.');
                    unset($return['transactions'][0]['source_id']);
                    $return['transactions'][0]['source_name'] = $originalSourceName;
                }
            }
        }

        if (-1 === bccomp($entry->transactionAmount, '0')) {
            // amount is negative: withdrawal or transfer.
            Log::debug('Amount is negative: assume transfer or withdrawal.');
            $return['transactions'][0]['amount'] = bcmul($entry->transactionAmount, '-1');

            // source is a Nordigen account
            // TODO entry may not exist, then what?
            $return['transactions'][0]['source_id'] = (int) $this->accounts[$accountId];

            // destination iban valid?
            $destinationIban = $entry->getDestinationIban() ?? '';
            if ('' !== $destinationIban && array_key_exists($destinationIban, $this->targetAccounts)) {
                // source is also an ID:
                Log::debug(sprintf('Recognized %s as a Firefly III asset account so this is a transfer.', $destinationIban));
                $return['transactions'][0]['destination_id'] = $this->targetAccounts[$destinationIban];
                $return['transactions'][0]['type']           = 'transfer';
            }
            // destination iban valid or doesn't exist:
            if ('' === $destinationIban || !array_key_exists($destinationIban, $this->targetAccounts)) {
                Log::debug(sprintf('"%s" is not a valid IBAN OR not recognized as Firefly III asset account so submitted as-is.', $destinationIban));
                // destination is the other side:
                $return['transactions'][0]['destination_name'] = $entry->getDestinationName() ?? '(unknown destination account)';
                $return['transactions'][0]['destination_iban'] = $entry->getDestinationIban() ?? null;
            }

            $mappedId = null;
            if (isset($return['transactions'][0]['destination_name'])) {
                Log::debug(sprintf('Check if "%s" is mapped to an account by the user.', $return['transactions'][0]['destination_name']));
                $mappedId = $this->getMappedAccountId($return['transactions'][0]['destination_name']);
            }

            if (null !== $mappedId && 0 !== $mappedId) {
                Log::debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $return['transactions'][0]['destination_name'], $mappedId));
                $mappedType = $this->getMappedAccountType($mappedId);

                $originalDestName                            = $return['transactions'][0]['destination_name'];
                $return['transactions'][0]['destination_id'] = $mappedId;
                // catch error here:
                try {
                    $return['transactions'][0]['type'] = $this->getTransactionType('asset', $mappedType);
                    Log::debug(sprintf('Transaction type seems to be %s', $return['transactions'][0]['type']));
                } catch (ImporterErrorException $e) {
                    Log::error($e->getMessage());
                    Log::info('Will not use mapped ID, Firefly III account is of the wrong type.');
                    unset($return['transactions'][0]['destination_id']);
                    $return['transactions'][0]['destination_name'] = $originalDestName;
                }
                app('log')->debug(sprintf('Parsed Nordigen transaction "%s".', $entry->transactionId), $return);
            }
        }

        app('log')->debug(sprintf('Parsed Nordigen transaction "%s".', $entry->transactionId));


        return $return;
    }

    /**
     * @param string $name
     *
     * @return int|null
     */
    private function getMappedAccountId(string $name): ?int
    {
        if (isset($this->configuration->getMapping()['accounts'][$name])) {
            return (int) $this->configuration->getMapping()['accounts'][$name];
        }

        return null;
    }

    /**
     * @param int $mappedId
     *
     * @return string
     */
    private function getMappedAccountType(int $mappedId): string
    {
        if (!isset($this->configuration->getAccountTypes()[$mappedId])) {
            app('log')->warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            $accountType             = $this->getAccountType($mappedId);
            $accountTypes            = $this->configuration->getAccountTypes();
            $accountTypes[$mappedId] = $accountType;
            $this->configuration->setAccountTypes($accountTypes);

            Log::debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $accountType));

            return $accountType;
        }
        $type = $this->configuration->getAccountTypes()[$mappedId] ?? 'expense';
        Log::debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $type));

        return $type;
    }

    /**
     * @param int $accountId
     *
     * @return string
     * @throws ImporterHttpException
     */
    private function getAccountType(int $accountId): string
    {
        $url   = (string) config('importer.url');
        $token = (string) config('importer.access_token');
        app('log')->debug(sprintf('Going to download account #%d', $accountId));
        $request = new GetAccountRequest($url, $token);
        $request->setId($accountId);
        /** @var GetAccountResponse $result */
        try {
            $result = $request->get();
        } catch (ApiHttpException $e) {
            throw new ImporterHttpException($e->getMessage(), 0, $e);
        }
        $type = $result->getAccount()->type;

        app('log')->debug(sprintf('Discovered that account #%d is of type "%s"', $accountId, $type));

        return $type;
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @return string
     * @throws ImporterErrorException
     */
    private function getTransactionType(string $source, string $destination): string
    {
        $combination = sprintf('%s-%s', $source, $destination);
        switch ($combination) {
            default:
                throw new ImporterErrorException(sprintf('Unknown combination: %s and %s', $source, $destination));
            case 'asset-liabilities':
            case 'asset-expense':
                return 'withdrawal';
            case 'asset-asset':
                return 'transfer';
            case 'liabilities-asset':
            case 'revenue-asset':
                return 'deposit';
        }
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
