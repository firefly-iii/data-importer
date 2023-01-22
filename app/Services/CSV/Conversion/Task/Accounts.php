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

/**
 * Class Accounts
 */
class Accounts extends AbstractTask
{
    use DeterminesTransactionType;

    /**
     * @param array $group
     *
     * @return array
     */
    public function process(array $group): array
    {
        app('log')->debug('Now in Accounts::process()');
        $total = count($group['transactions']);
        foreach ($group['transactions'] as $index => $transaction) {
            app('log')->debug(sprintf('Now processing transaction %d of %d', $index + 1, $total));
            $group['transactions'][$index] = $this->processTransaction($transaction);
        }

        return $group;
    }

    /**
     * Returns true if the task requires the default account.
     *
     * @return bool
     */
    public function requiresDefaultAccount(): bool
    {
        return true;
    }

    /**
     * Returns true if the task requires the default currency of the user.
     *
     * @return bool
     */
    public function requiresTransactionCurrency(): bool
    {
        return false;
    }

    /**
     * @param array $transaction
     *
     * @return array
     * @throws ImporterErrorException
     */
    private function processTransaction(array $transaction): array
    {
        app('log')->debug('Now in Accounts::processTransaction()');

        /*
         * Try to find the source and destination accounts in the transaction.
         *
         * The source account will default back to the user's submitted default account.
         * So when everything fails, the transaction will be a expense for amount X.
         */
        $sourceArray = $this->getSourceArray($transaction);
        $destArray   = $this->getDestinationArray($transaction);
        $source      = $this->findAccount($sourceArray, $this->account);
        $destination = $this->findAccount($destArray, null);

        /*
         * First, set source and destination in the transaction array:
         */
        $transaction         = $this->setSource($transaction, $source);
        $transaction         = $this->setDestination($transaction, $destination);
        $transaction['type'] = $this->determineType($source['type'], $destination['type']);
        app('log')->debug(sprintf('Transaction type is set to "%s"', $transaction['type']));
        app('log')->debug('Source is now:', $source);
        app('log')->debug('Destination is now:', $destination);

        $amount = (string)$transaction['amount'];
        $amount = '' === $amount ? '0' : $amount;

        if ('0' === $amount) {
            app('log')->error('Amount is ZERO. This will give trouble further down the line.');
        }

        /*
         * If the amount is positive, the transaction is a deposit. We switch Source
         * and Destination and see if we can still handle the transaction, but only if the transaction
         * isn't already a deposit (it has to be a withdrawal).
         *
         */
        if ('withdrawal' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            // amount is positive
            app('log')->debug(sprintf('%s is positive and type is "%s", switch source/destination', $amount, $transaction['type']));
            $transaction         = $this->setSource($transaction, $destination);
            $transaction         = $this->setDestination($transaction, $source);
            $transaction['type'] = $this->determineType($destination['type'], $source['type']);
            app('log')->debug('Source is now:', $destination); // yes this is correct.
            app('log')->debug('Destination is now:', $source); // yes this is correct.

            // switch variables because processing further ahead will otherwise be messed up:
            [$source, $destination] = [$destination, $source];
        }

        /*
         * If the amount is positive and the type is a transfer, switch accounts around.
         */
        if ('transfer' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            app('log')->debug('Transaction is a transfer, and amount is positive, will switch accounts.');
            $transaction = $this->setSource($transaction, $destination);
            $transaction = $this->setDestination($transaction, $source);
            app('log')->debug('Source is now:', $destination); // yes this is correct!
            app('log')->debug('Destination is now:', $source); // yes this is correct!
        }

        /*
         * If deposit and amount is positive, do nothing.
         */
        if ('deposit' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            app('log')->debug('Transaction is a deposit, and amount is positive. Will not change account types.');
        }

        /*
         * If deposit and amount is positive, but the source is not a revenue, fall back to
         * some "original-field-name" values (if they exist) and hope for the best.
         */
        if (
            'deposit' === $transaction['type'] && 1 === bccomp($amount, '0') && 'revenue' !== $source['type'] && '' !== (string)$source['type']
        ) {
            app('log')->warning(
                sprintf(
                    'Transaction is a deposit, and amount is positive, but source is not a revenue ("%s"). Will fall back to original field names.',
                    $source['type']
                )
            );
            $newSource   = [
                'id'     => null,
                'name'   => $transaction['original-opposing-name'] ?? '(no name)',
                'iban'   => $transaction['original-opposing-iban'] ?? null,
                'number' => $transaction['original-opposing-number'] ?? null,
                'bic'    => null,
            ];
            $transaction = $this->setSource($transaction, $newSource);
        }

        /*
         * If amount is negative and type is transfer, make sure accounts are "original".
         */
        if ('transfer' === $transaction['type'] && -1 === bccomp($amount, '0')) {
            app('log')->debug('Transaction is a transfer, and amount is negative, must not change accounts.');
            $transaction = $this->setSource($transaction, $source);
            $transaction = $this->setDestination($transaction, $destination);
            app('log')->debug('Source is now:', $source);
            app('log')->debug('Destination is now:', $destination);
        }

        /*
         * Final check. If the type is "withdrawal" but the destination account found is "revenue"
         * we found the wrong one. Just submit the name and hope for the best.
         */
        if ('revenue' === $destination['type'] && 'withdrawal' === $transaction['type']) {
            app('log')->warning('The found destination account is of type revenue but this is a withdrawal. Out of cheese error.');
            app('log')->debug(
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
            app('log')->warning('The found source account is of type expense but this is a deposit. Out of cheese error.');
            app('log')->debug(sprintf('Data importer will submit name "%s" and IBAN "%s" and let Firefly III sort it out.', $source['name'], $source['iban']));
            $transaction['source_id']   = null;
            $transaction['source_name'] = $source['name'];
            $transaction['source_iban'] = $source['iban'];
        }

        /*
         * if new source or destination ID is filled in, drop the other fields:
         */
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

        return $transaction;
    }

    /**
     * @param array $transaction
     *
     * @return array
     */
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

    /**
     * @param array $transaction
     *
     * @return array
     */
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
     * @param array        $array
     *
     * @param Account|null $defaultAccount
     *
     * @return array
     * @throws ImporterErrorException
     */
    private function findAccount(array $array, ?Account $defaultAccount): array
    {
        app('log')->debug('Now in findAccount', $array);
        if (null === $defaultAccount) {
            app('log')->debug('findAccount() default account is NULL.');
        }
        if (null !== $defaultAccount) {
            app('log')->debug(sprintf('Default account is #%d ("%s")', $defaultAccount->id, $defaultAccount->name));
        }

        $result = null;
        // if the ID is set, at least search for the ID.
        if (is_int($array['id']) && $array['id'] > 0) {
            app('log')->debug('Find by ID field.');
            $result = $this->findById((string)$array['id']);
        }
        if (null !== $result) {
            $return = $result->toArray();
            app('log')->debug('Result of findById is not null, returning:', $return);

            return $return;
        }

        // if the IBAN is set, search for the IBAN.
        if (isset($array['iban']) && '' !== (string)$array['iban']) {
            app('log')->debug('Find by IBAN.');
            $transactionType = (string)($array['transaction_type'] ?? null);
            $result          = $this->findByIban((string)$array['iban'], $transactionType);
        }
        if (null !== $result) {
            $return = $result->toArray();
            app('log')->debug('Result of findByIBAN is not null, returning:', $return);

            return $return;
        }
        // If the IBAN search result is NULL, but the IBAN itself is not null,
        // data importer will return an array with the IBAN (and optionally the name).

        // if the account number is set, search for the account number.
        if (isset($array['number']) && '' !== (string)$array['number']) {
            app('log')->debug('Find by account number.');
            $transactionType = (string)($array['transaction_type'] ?? null);
            $result          = $this->findByNumber((string)$array['number'], $transactionType);
        }
        if (null !== $result) {
            $return = $result->toArray();
            app('log')->debug('Result of findByNumber is not null, returning:', $return);

            return $return;
        }


        // find by name, return only if it's an asset or liability account.
        if (isset($array['name']) && '' !== (string)$array['name']) {
            app('log')->debug('Find by name.');
            $result = $this->findByName((string)$array['name']);
        }
        if (null !== $result) {
            $return = $result->toArray();
            app('log')->debug('Result of findByName is not null, returning:', $return);

            return $return;
        }

        app('log')->debug('Found no account or haven\'t searched for one.');

        // append an empty type to the array for consistency's sake.
        $array['type'] = $array['type'] ?? null;
        $array['bic']  = $array['bic'] ?? null;

        // Return ID or name if not null
        if (null !== $array['id'] || '' !== (string)$array['name']) {
            app('log')->debug('Array with account has some name info, return that.', $array);

            return $array;
        }

        // Return ID or IBAN if not null
        if ('' !== (string)$array['iban']) {
            app('log')->debug('Array with account has some IBAN info, return that.', $array);

            return $array;
        }

        // if the default account is not NULL, return that one instead:
        if (null !== $defaultAccount) {
            $default = $defaultAccount->toArray();
            app('log')->debug('Default account is not null, so will return:', $default);

            return $default;
        }
        app('log')->debug('Default account is NULL, so will return: ', $array);

        return $array;
    }

    /**
     * @param string $value
     *
     * @return Account|null
     * @throws ImporterErrorException
     */
    private function findById(string $value): ?Account
    {
        app('log')->debug(sprintf('Going to search account with ID "%s"', $value));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('id');
        $request->setQuery($value);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (1 === count($response)) {
            /** @var Account $account */
            try {
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }

            app('log')->debug(sprintf('[a] Found %s account #%d based on ID "%s"', $account->type, $account->id, $value));

            return $account;
        }

        app('log')->debug('Found NOTHING in findById.');

        return null;
    }

    /**
     * @param string $iban
     * @param string $transactionType
     *
     * @return Account|null
     * @throws ImporterErrorException
     */
    private function findByIban(string $iban, string $transactionType): ?Account
    {
        app('log')->debug(sprintf('Going to search account with IBAN "%s"', $iban));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('iban');
        $request->setQuery($iban);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            app('log')->debug('Found NOTHING in findbyiban.');

            return null;
        }

        if (1 === count($response)) {
            /** @var Account $account */
            try {
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }
            // catch impossible combination "expense" with "deposit"
            if ('expense' === $account->type && 'deposit' === $transactionType) {
                app('log')->debug(
                    sprintf(
                        'Out of cheese error (IBAN). Found Found %s account #%d based on IBAN "%s". But not going to use expense/deposit combi.',
                        $account->type,
                        $account->id,
                        $iban
                    )
                );
                app('log')->debug('Firefly III will have to make the correct decision.');

                return null;
            }
            app('log')->debug(sprintf('[a] Found %s account #%d based on IBAN "%s"', $account->type, $account->id, $iban));

            // to fix issue #4293, Firefly III will ignore this account if it's an expense or a revenue account.
            if (in_array($account->type, ['expense', 'revenue'], true)) {
                app('log')->debug('[a] Data importer will pretend not to have found anything. Firefly III must handle the IBAN.');

                return null;
            }


            return $account;
        }

        if (2 === count($response)) {
            app('log')->debug('Found 2 results, Firefly III will have to make the correct decision.');

            return null;
        }
        app('log')->debug(sprintf('Found %d result(s), Firefly III will have to make the correct decision.', count($response)));

        return null;
    }

    /**
     * @param string $accountNumber
     * @param string $transactionType
     *
     * @return Account|null
     * @throws ImporterErrorException
     */
    private function findByNumber(string $accountNumber, string $transactionType): ?Account
    {
        app('log')->debug(sprintf('Going to search account with account number "%s"', $accountNumber));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('number');
        $request->setQuery($accountNumber);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            app('log')->debug('Found NOTHING in findbynumber.');

            return null;
        }

        if (1 === count($response)) {
            /** @var Account $account */
            try {
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImporterErrorException($e->getMessage());
            }
            // catch impossible combination "expense" with "deposit"
            if ('expense' === $account->type && 'deposit' === $transactionType) {
                app('log')->debug(
                    sprintf(
                        'Out of cheese error (account number). Found Found %s account #%d based on account number "%s". But not going to use expense/deposit combi.',
                        $account->type,
                        $account->id,
                        $accountNumber
                    )
                );
                app('log')->debug('Firefly III will have to make the correct decision.');

                return null;
            }
            app('log')->debug(sprintf('[a] Found %s account #%d based on account number "%s"', $account->type, $account->id, $accountNumber));

            // to fix issue #4293, Firefly III will ignore this account if it's an expense or a revenue account.
            if (in_array($account->type, ['expense', 'revenue'], true)) {
                app('log')->debug('[a] Data importer will pretend not to have found anything. Firefly III must handle the account number.');

                return null;
            }


            return $account;
        }

        if (2 === count($response)) {
            app('log')->debug('Found 2 results, Firefly III will have to make the correct decision.');

            return null;
        }
        app('log')->debug(sprintf('Found %d result(s), Firefly III will have to make the correct decision.', count($response)));

        return null;
    }

    /**
     * @param string $name
     *
     * @return Account|null
     * @throws ImporterErrorException
     */
    private function findByName(string $name): ?Account
    {
        app('log')->debug(sprintf('Going to search account with name "%s"', $name));
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setField('name');
        $request->setQuery($name);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImporterErrorException($e->getMessage());
        }
        if (0 === count($response)) {
            app('log')->debug('Found NOTHING in findbyname.');

            return null;
        }
        /** @var Account $account */
        foreach ($response as $account) {
            if (in_array($account->type, [AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE], true)
                && strtolower($account->name) === strtolower($name)) {
                app('log')->debug(sprintf('[b] Found "%s" account #%d based on name "%s"', $account->type, $account->id, $name));

                return $account;
            }
        }
        app('log')->debug(
            sprintf('Found %d account(s) searching for "%s" but not going to use them. Firefly III must handle the values.', count($response), $name)
        );

        return null;
    }

    /**
     * @param array $transaction
     * @param array $source
     *
     * @return array
     */
    private function setSource(array $transaction, array $source): array
    {
        return $this->setTransactionAccount('source', $transaction, $source);
    }

    /**
     * @param string $direction
     * @param array  $transaction
     * @param array  $account
     *
     * @return array
     */
    private function setTransactionAccount(string $direction, array $transaction, array $account): array
    {
        $transaction[sprintf('%s_id', $direction)]     = $account['id'];
        $transaction[sprintf('%s_name', $direction)]   = $account['name'];
        $transaction[sprintf('%s_iban', $direction)]   = $account['iban'];
        $transaction[sprintf('%s_number', $direction)] = $account['number'];
        $transaction[sprintf('%s_bic', $direction)]    = $account['bic'];

        return $transaction;
    }

    /**
     * @param array $transaction
     * @param array $source
     *
     * @return array
     */
    private function setDestination(array $transaction, array $source): array
    {
        return $this->setTransactionAccount('destination', $transaction, $source);
    }
}
