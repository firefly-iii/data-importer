<?php

/*
 * Accounts.php
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

namespace App\Services\CSV\Conversion\Task;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Conversion\Support\DeterminesTransactionType;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiException;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException as GrumpyApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\AccountType;
use GrumpyDictator\FFIIIApiSupport\Request\GetSearchAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class Accounts
 */
class Accounts extends AbstractTask
{
    use DeterminesTransactionType;

    /**
     * @throws ImporterErrorException
     */
    public function process(array $group): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $total = count($group['transactions']);
        foreach ($group['transactions'] as $index => $transaction) {
            Log::debug(sprintf('Now processing transaction %d of %d', $index + 1, $total));
            $group['transactions'][$index] = $this->processTransaction($transaction);
        }

        return $group;
    }

    /**
     * @throws ImporterErrorException
     */
    private function processTransaction(array $transaction): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        /*
         * Try to find the source and destination accounts in the transaction.
         *
         * The source account will default back to the user's submitted default account.
         * So when everything fails, the transaction will be an expense for amount X.
         */
        $sourceArray         = $this->getSourceArray($transaction);
        $destArray           = $this->getDestinationArray($transaction);
        $source              = $this->findAccount($sourceArray, $this->account);
        $destination         = $this->findAccount($destArray, null);

        // First, set source and destination in the transaction array:
        $transaction         = $this->setSource($transaction, $source);
        $transaction         = $this->setDestination($transaction, $destination);
        $transaction['type'] = $this->determineType($source['type'], $destination['type']);
        Log::debug(sprintf('Transaction type is set to "%s"', $transaction['type']));
        Log::debug('Source is now:', $source);
        Log::debug('Destination is now:', $destination);

        $amount              = (string) $transaction['amount'];
        $amount              = '' === $amount ? '0' : $amount;

        if ('0' === $amount) {
            Log::error('Amount is ZERO. This will give trouble further down the line.');
        }

        /*
         * If the amount is positive, the transaction is a deposit. We switch Source
         * and Destination and see if we can still handle the transaction, but only if the transaction
         * isn't already a deposit (it has to be a withdrawal).
         *
         */
        if ('withdrawal' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            // amount is positive
            Log::debug(sprintf('%s is positive and type is "%s", switch source/destination', $amount, $transaction['type']));
            $transaction            = $this->setSource($transaction, $destination);
            $transaction            = $this->setDestination($transaction, $source);
            $transaction['type']    = $this->determineType($destination['type'], $source['type']);
            Log::debug('Source is now:', $destination); // yes this is correct.
            Log::debug('Destination is now:', $source); // yes this is correct.

            // switch variables because processing further ahead will otherwise be messed up:
            [$source, $destination] = [$destination, $source];
        }

        // If the amount is positive and the type is a transfer, switch accounts around.
        if ('transfer' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            Log::debug('Transaction is a transfer, and amount is positive, will switch accounts.');
            $transaction = $this->setSource($transaction, $destination);
            $transaction = $this->setDestination($transaction, $source);
            Log::debug('Source is now:', $destination); // yes this is correct!
            Log::debug('Destination is now:', $source); // yes this is correct!

            // also switch amount and foreign currency amount, if both are present.
            // if this data is missing, Firefly III will break later either way.
            if ($this->hasAllAmountInformation($transaction)) {
                Log::debug('This transfer has all necessary (foreign) currency + amount information, so swap these too.');
                $transaction = $this->swapCurrencyInformation($transaction);
            }
        }

        // If deposit and amount is positive, do nothing.
        if ('deposit' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            Log::debug('Transaction is a deposit, and amount is positive. Will not change account types.');
        }

        /*
         * If deposit and amount is positive, but the source is not a revenue, fall back to
         * some "original-field-name" values (if they exist) and hope for the best.
         */
        if ('deposit' === $transaction['type'] && 1 === bccomp($amount, '0') && 'revenue' !== $source['type'] && '' !== (string) $source['type']) {
            Log::warning(sprintf('Transaction is a deposit, and amount is positive, but source is not a revenue ("%s"). Will fall back to original field names.', $source['type']));
            $newSource   = [
                'id'     => null,
                'name'   => $transaction['original-opposing-name'] ?? '(no name)',
                'iban'   => $transaction['original-opposing-iban'] ?? null,
                'number' => $transaction['original-opposing-number'] ?? null,
                'bic'    => null,
            ];
            $transaction = $this->setSource($transaction, $newSource);
        }

        // If amount is negative and type is transfer, make sure accounts are "original".
        if ('transfer' === $transaction['type'] && -1 === bccomp($amount, '0')) {
            Log::debug('Transaction is a transfer, and amount is negative, must not change accounts.');
            $transaction = $this->setSource($transaction, $source);
            $transaction = $this->setDestination($transaction, $destination);
            Log::debug('Source is now:', $source);
            Log::debug('Destination is now:', $destination);
        }

        /*
         * Final check. If the type is "withdrawal" but the destination account found is "revenue"
         * we found the wrong one. Just submit the name and hope for the best.
         */
        if ('revenue' === $destination['type'] && 'withdrawal' === $transaction['type']) {
            Log::warning('The found destination account is of type revenue but this is a withdrawal. Out of cheese error.');
            Log::debug(
                sprintf('Data importer will submit name "%s" and IBAN "%s" and let Firefly III sort it out.', $destination['name'], $destination['iban'])
            );
            $transaction['destination_id']   = null;
            $transaction['destination_name'] = $destination['name'];
            $transaction['destination_iban'] = $destination['iban'];
        }

        /*
         * Same but for the other way around.
         * If type is "deposit" but the source account is an expense account.
         * Submit just the name.
         */
        if ('expense' === $source['type'] && 'deposit' === $transaction['type']) {
            Log::warning('The found source account is of type expense but this is a deposit. Out of cheese error.');
            Log::debug(sprintf('Data importer will submit name "%s" and IBAN "%s" and let Firefly III sort it out.', $source['name'], $source['iban']));
            $transaction['source_id']   = null;
            $transaction['source_name'] = $source['name'];
            $transaction['source_iban'] = $source['iban'];
        }

        // if new source or destination ID is filled in, drop the other fields:
        if (0 !== $transaction['source_id'] && null !== $transaction['source_id']) {
            $transaction['source_name']   = null;
            $transaction['source_iban']   = null;
            $transaction['source_number'] = null;
        }
        if (0 !== $transaction['destination_id'] && null !== $transaction['destination_id']) {
            $transaction['destination_name']   = null;
            $transaction['destination_iban']   = null;
            $transaction['destination_number'] = null;
        }
        if ($this->hasAllCurrencies($transaction)) {
            Log::debug('Final validation of foreign amount and or normal transaction amount');
            // withdrawal
            if ('withdrawal' === $transaction['type']) {
                // currency info must match $source
                // so if we can switch them around we will.
                if ($transaction['currency_code'] !== $source['currency_code']
                    && $transaction['foreign_currency_code'] === $source['currency_code']) {
                    Log::debug('Source account accepts %s, so foreign / native numbers are switched now.');
                    $amount                               = $transaction['amount'] ?? '0';
                    $currency                             = $transaction['currency_code'] ?? '';
                    $transaction['amount']                = $transaction['foreign_amount'] ?? '0';
                    $transaction['currency_code']         = $transaction['foreign_currency_code'] ?? '';
                    $transaction['foreign_amount']        = $amount;
                    $transaction['foreign_currency_code'] = $currency;
                }
            }
            // deposit
            if ('deposit' === $transaction['type']) {
                // currency info must match $destination,
                // so if we can switch them around we will.
                if ($transaction['currency_code'] !== $destination['currency_code']
                    && $transaction['foreign_currency_code'] === $destination['currency_code']) {
                    Log::debug('Destination account accepts %s, so foreign / native numbers are switched now.');
                    $amount                               = $transaction['amount'] ?? '0';
                    $currency                             = $transaction['currency_code'] ?? '';
                    $transaction['amount']                = $transaction['foreign_amount'] ?? '0';
                    $transaction['currency_code']         = $transaction['foreign_currency_code'] ?? '';
                    $transaction['foreign_amount']        = $amount;
                    $transaction['foreign_currency_code'] = $currency;
                }
            }

            Log::debug('Final validation of foreign amount and or normal transaction amount finished.');
        }

        return $transaction;
    }

    private function getSourceArray(array $transaction): array
    {
        return [
            'transaction_type' => $transaction['type'],
            'id'               => $transaction['source_id'],
            'name'             => $transaction['source_name'],
            'iban'             => $transaction['source_iban'] ?? null,
            'number'           => $transaction['source_number'] ?? null,
            'bic'              => $transaction['source_bic'] ?? null,
            'direction'        => 'source',
        ];
    }

    private function getDestinationArray(array $transaction): array
    {
        return [
            'transaction_type' => $transaction['type'],
            'id'               => $transaction['destination_id'],
            'name'             => $transaction['destination_name'],
            'iban'             => $transaction['destination_iban'] ?? null,
            'number'           => $transaction['destination_number'] ?? null,
            'bic'              => $transaction['destination_bic'] ?? null,
            'direction'        => 'destination',
        ];
    }

    /**
     * @throws ImporterErrorException
     */
    private function findAccount(array $array, ?Account $defaultAccount): array
    {
        Log::debug('Now in findAccount', $array);
        if (!$defaultAccount instanceof Account) {
            Log::debug('findAccount() default account is NULL.');
        }
        if ($defaultAccount instanceof Account) {
            Log::debug(sprintf('Default account is #%d ("%s")', $defaultAccount->id, $defaultAccount->name));
        }

        $result = null;

        if (array_key_exists('id', $array) && null === $array['id']) {
            Log::debug('ID field is NULL, will not search for it.');
        }

        // if the ID is set, at least search for the ID.
        if (array_key_exists('id', $array) && is_int($array['id']) && $array['id'] > 0) {
            Log::debug('Will search by ID field.');
            $result = $this->findById((string) $array['id']);
        }
        if ($result instanceof Account) {
            $return = $result->toArray();
            Log::debug('Result of findById is not null, returning:', $return);

            return $return;
        }

        // if the IBAN is set, search for the IBAN.
        if (array_key_exists('iban', $array) && '' !== (string) $array['iban']) {
            Log::debug('Will search by IBAN.');
            $transactionType = (string) ($array['transaction_type'] ?? null);
            $result          = $this->findByIban((string) $array['iban'], $transactionType);
        }
        if ($result instanceof Account) {
            $return = $result->toArray();
            Log::debug('Result of findByIBAN is not null, returning:', $return);

            return $return;
        }
        if (array_key_exists('iban', $array) && null === $array['iban']) {
            Log::debug('IBAN field is NULL, will not search for it.');
        }
        // If the IBAN search result is NULL, but the IBAN itself is not null,
        // data importer will return an array with the IBAN (and optionally the name).

        // if the account number is set, search for the account number.
        if (array_key_exists('number', $array) && '' !== (string) $array['number']) {
            Log::debug('Search by account number.');
            $transactionType = (string) ($array['transaction_type'] ?? null);
            $result          = $this->findByNumber((string) $array['number'], $transactionType);
        }
        if ($result instanceof Account) {
            $return = $result->toArray();
            Log::debug('Result of findByNumber is not null, returning:', $return);

            return $return;
        }
        if (array_key_exists('number', $array) && null === $array['number']) {
            Log::debug('Number field is NULL, will not search for it.');
        }

        // find by name, return only if it's an asset or liability account.
        if (array_key_exists('name', $array)  && '' !== (string) $array['name']) {
            Log::debug('Search by name.');
            $result = $this->findByName((string) $array['name']);
        }
        if ($result instanceof Account) {
            $return = $result->toArray();
            Log::debug('Result of findByName is not null, returning:', $return);

            return $return;
        }
        if (array_key_exists('name', $array) && null === $array['name']) {
            Log::debug('Name field is NULL, will not search for it.');
        }

        Log::debug('Found no account or haven\'t searched for one because of missing data.');

        // append an empty type to the array for consistency's sake.
        $array['type'] ??= null;
        $array['bic']  ??= null;
        $array['currency_code']  ??= null;

        // Return ID or name if not null
        if (null !== $array['id'] || '' !== (string) $array['name']) {
            Log::debug('At least the array with account-info has some name info, return that.', $array);

            return $array;
        }

        // Return ID or IBAN if not null
        if ('' !== (string) $array['iban']) {
            Log::debug('At least the with account-info has some IBAN info, return that.', $array);

            return $array;
        }

        // Return ID or number if not null
        if ('' !== (string) $array['number']) {
            Log::debug('At least the array with account-info has some account number info, return that.', $array);

            return $array;
        }

        // if the default account is not NULL, return that one instead:
        if ($defaultAccount instanceof Account) {
            $default = $defaultAccount->toArray();
            Log::debug('At least the default account is not null, so will return that:', $default);

            return $default;
        }
        Log::debug('The default account is NULL, so will return what we started with: ', $array);

        return $array;
    }

    /**
     * @throws ImporterErrorException
     */
    private function findById(string $value): ?Account
    {
        Log::debug(sprintf('Going to search account with ID "%s"', $value));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('id');
        $request->setQuery($value);

        try {
            /** @var GetAccountsResponse $response */
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (1 === count($response)) {
            try {
                /** @var Account $account */
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }

            Log::debug(sprintf('[a] Found %s account #%d based on ID "%s"', $account->type, $account->id, $value));

            return $account;
        }

        Log::debug('Found NOTHING in findById.');

        return null;
    }

    /**
     * @throws ImporterErrorException
     */
    private function findByIban(string $iban, string $transactionType): ?Account
    {
        Log::debug(sprintf('Going to search account with IBAN "%s"', $iban));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('iban');
        $request->setQuery($iban);

        try {
            /** @var GetAccountsResponse $response */
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            Log::debug('Found NOTHING in findbyiban.');

            return null;
        }

        if (1 === count($response)) {
            try {
                /** @var Account $account */
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }
            // catch impossible combination "expense" with "deposit"
            if ('expense' === $account->type && 'deposit' === $transactionType) {
                Log::debug(
                    sprintf(
                        'Out of cheese error (IBAN). Found Found %s account #%d based on IBAN "%s". But not going to use expense/deposit combi.',
                        $account->type,
                        $account->id,
                        $iban
                    )
                );
                Log::debug('Firefly III will have to make the correct decision.');

                return null;
            }
            Log::debug(sprintf('[a] Found %s account #%d based on IBAN "%s"', $account->type, $account->id, $iban));

            // to fix issue #4293, Firefly III will ignore this account if it's an expense or a revenue account.
            if (in_array($account->type, ['expense', 'revenue'], true)) {
                Log::debug('[a] Data importer will pretend not to have found anything. Firefly III must handle the IBAN.');

                return null;
            }

            return $account;
        }

        if (2 === count($response)) {
            Log::debug('Found 2 results, Firefly III will have to make the correct decision.');

            return null;
        }
        Log::debug(sprintf('Found %d result(s), Firefly III will have to make the correct decision.', count($response)));

        return null;
    }

    /**
     * @throws ImporterErrorException
     */
    private function findByNumber(string $accountNumber, string $transactionType): ?Account
    {
        Log::debug(sprintf('Going to search account with account number "%s"', $accountNumber));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('number');
        $request->setQuery($accountNumber);

        try {
            /** @var GetAccountsResponse $response */
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            Log::debug('Found NOTHING in findbynumber.');

            return null;
        }

        if (1 === count($response)) {
            try {
                /** @var Account $account */
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }
            // catch impossible combination "expense" with "deposit"
            if ('expense' === $account->type && 'deposit' === $transactionType) {
                Log::debug(
                    sprintf(
                        'Out of cheese error (account number). Found Found %s account #%d based on account number "%s". But not going to use expense/deposit combi.',
                        $account->type,
                        $account->id,
                        $accountNumber
                    )
                );
                Log::debug('Firefly III will have to make the correct decision.');

                return null;
            }
            Log::debug(sprintf('[a] Found %s account #%d based on account number "%s"', $account->type, $account->id, $accountNumber));

            // to fix issue #4293, Firefly III will ignore this account if it's an expense or a revenue account.
            if (in_array($account->type, ['expense', 'revenue'], true)) {
                Log::debug('[a] Data importer will pretend not to have found anything. Firefly III must handle the account number.');

                return null;
            }

            return $account;
        }

        if (2 === count($response)) {
            Log::debug('Found 2 results, Firefly III will have to make the correct decision.');

            return null;
        }
        Log::debug(sprintf('Found %d result(s), Firefly III will have to make the correct decision.', count($response)));

        return null;
    }

    /**
     * @throws ImporterErrorException
     */
    private function findByName(string $name): ?Account
    {
        Log::debug(sprintf('Going to search account with name "%s"', $name));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('name');
        $request->setQuery($name);

        try {
            /** @var GetAccountsResponse $response */
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            Log::debug('Found NOTHING in findbyname.');

            return null;
        }

        /** @var Account $account */
        foreach ($response as $account) {
            if (in_array($account->type, [AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE], true)
                && strtolower((string) $account->name) === strtolower($name)) {
                Log::debug(sprintf('[b] Found "%s" account #%d based on name "%s"', $account->type, $account->id, $name));

                return $account;
            }
        }
        Log::debug(
            sprintf('Found %d account(s) searching for "%s" but not going to use them. Firefly III must handle the values.', count($response), $name)
        );

        return null;
    }

    private function setSource(array $transaction, array $source): array
    {
        return $this->setTransactionAccount('source', $transaction, $source);
    }

    private function setTransactionAccount(string $direction, array $transaction, array $account): array
    {
        $transaction[sprintf('%s_id', $direction)]     = $account['id'];
        $transaction[sprintf('%s_name', $direction)]   = $account['name'];
        $transaction[sprintf('%s_iban', $direction)]   = $account['iban'];
        $transaction[sprintf('%s_number', $direction)] = $account['number'];
        $transaction[sprintf('%s_bic', $direction)]    = $account['bic'];

        return $transaction;
    }

    private function setDestination(array $transaction, array $source): array
    {
        return $this->setTransactionAccount('destination', $transaction, $source);
    }

    /**
     * Basic check for currency info.
     */
    private function hasAllAmountInformation(array $transaction): bool
    {
        return
            array_key_exists('amount', $transaction)
            && array_key_exists('foreign_amount', $transaction)
            && (array_key_exists('foreign_currency_code', $transaction) || array_key_exists('foreign_currency_id', $transaction))
            && (array_key_exists('currency_code', $transaction) || array_key_exists('currency_id', $transaction));
    }

    private function swapCurrencyInformation(array $transaction): array
    {
        // swap amount and foreign amount:
        $amount                        = $transaction['amount'];
        $transaction['amount']         = $transaction['foreign_amount'];
        $transaction['foreign_amount'] = $amount;
        Log::debug(sprintf('Amount is now %s', $transaction['amount']));
        Log::debug(sprintf('Foreign is now %s', $transaction['foreign_amount']));

        // swap currency ID and foreign currency ID, if both exist:
        if (array_key_exists('currency_id', $transaction) && array_key_exists('foreign_currency_id', $transaction)) {
            $currencyId                         = $transaction['currency_id'];
            $transaction['currency_id']         = $transaction['foreign_currency_id'];
            $transaction['foreign_currency_id'] = $currencyId;
            Log::debug(sprintf('Currency ID is now %d', $transaction['currency_id']));
            Log::debug(sprintf('Foreign currency ID is now %d', $transaction['foreign_currency_id']));
        }
        // swap currency code and foreign currency code, if both exist:
        if (array_key_exists('currency_code', $transaction) && array_key_exists('foreign_currency_code', $transaction)) {
            $currencyCode                         = $transaction['currency_code'];
            $transaction['currency_code']         = $transaction['foreign_currency_code'];
            $transaction['foreign_currency_code'] = $currencyCode;
            Log::debug(sprintf('Currency code is now %s', $transaction['currency_code']));
            Log::debug(sprintf('Foreign currency code is now %s', $transaction['foreign_currency_code']));
        }

        return $transaction;
    }

    private function hasAllCurrencies(array $transaction): bool
    {
        $transaction['foreign_currency_code'] ??= '';
        $transaction['currency_code']         ??= '';
        $transaction['amount']                ??= '';
        $transaction['foreign_amount']        ??= '';

        return '' !== (string) $transaction['currency_code'] && '' !== (string) $transaction['foreign_currency_code']
               && '' !== (string) $transaction['amount']
               && '' !== (string) $transaction['foreign_amount'];
    }

    /**
     * Returns true if the task requires the default account.
     */
    public function requiresDefaultAccount(): bool
    {
        return true;
    }

    /**
     * Returns true if the task requires the primary currency of the user.
     */
    public function requiresTransactionCurrency(): bool
    {
        return false;
    }
}
