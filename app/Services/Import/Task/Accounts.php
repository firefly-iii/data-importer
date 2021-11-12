<?php
declare(strict_types=1);
/**
 * Accounts.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\Import\Task;

use App\Exceptions\ImportException;
use App\Services\Import\DeterminesTransactionType;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiException;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException as GrumpyApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\AccountType;
use GrumpyDictator\FFIIIApiSupport\Request\GetSearchAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;
use Log;

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
        Log::debug('Now in Accounts::process()');
        $total = count($group['transactions']);
        foreach ($group['transactions'] as $index => $transaction) {
            Log::debug(sprintf('Now processing transaction %d of %d', $index + 1, $total));
            $group['transactions'][$index] = $this->processTransaction($transaction);
        }

        return $group;
    }

    /**
     * @param array $transaction
     *
     * @return array
     */
    private function processTransaction(array $transaction): array
    {
        Log::debug('Now in Accounts::processTransaction()');

        /*
         * Try to find the source and destination accounts in the transaction.
         *
         * The source account will default back to the user's submitted default account.
         * So when everything fails, the transaction will be a deposit for amount X.
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

        $amount = (string) $transaction['amount'];
        $amount = '' === $amount ? '0' : $amount;

        if ('0' === $amount) {
            Log::error('Amount is ZERO. This will give trouble further down the line.');
        }

        /*
         * If the amount is positive, the transaction is a deposit. We switch Source
         * and Destination and see if we can still handle the transaction, but only if the transaction
         * isn't already a deposit
         */
        if ('deposit' !== $transaction['type'] && 1 === bccomp($amount, '0')) {
            // amount is positive
            Log::debug(sprintf('%s is positive.', $amount));
            $transaction         = $this->setSource($transaction, $destination);
            $transaction         = $this->setDestination($transaction, $source);
            $transaction['type'] = $this->determineType($destination['type'], $source['type']);
        }
        if ('deposit' === $transaction['type'] && 1 === bccomp($amount, '0')) {
            Log::debug('Transaction is a deposit, and amount is positive. Will not change account types.');
        }

        /*
         * Final check. If the type is "withdrawal" but the destination account found is "revenue"
         * we found the wrong one. Just submit the name and hope for the best.
         */
        if ('revenue' === $destination['type'] && 'withdrawal' === $transaction['type']) {
            Log::warning('The found destination account is of type revenue but this is a withdrawal. Out of cheese error.');
            Log::debug(
                sprintf('CSV importer will submit name "%s" and IBAN "%s" and let Firefly III sort it out.', $destination['name'], $destination['iban'])
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
            Log::debug(sprintf('CSV importer will submit name "%s" and IBAN "%s" and let Firefly III sort it out.', $source['name'], $source['iban']));
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
     * @throws ImportException
     */
    private function findAccount(array $array, ?Account $defaultAccount): array
    {
        Log::debug('Now in findAccount', $array);
        if (null === $defaultAccount) {
            Log::debug('findAccount() default account is NULL.');
        }
        if (null !== $defaultAccount) {
            Log::debug(sprintf('Default account is #%d ("%s")', $defaultAccount->id, $defaultAccount->name));
        }

        $result = null;
        // if the ID is set, at least search for the ID.
        if (is_int($array['id']) && $array['id'] > 0) {
            Log::debug('Find by ID field.');
            $result = $this->findById((string) $array['id']);
        }
        if (null !== $result) {
            $return = $result->toArray();
            Log::debug('Result of findById is not null, returning:', $return);

            return $return;
        }

        // if the IBAN is set, search for the IBAN.
        if (isset($array['iban']) && '' !== (string) $array['iban']) {
            Log::debug('Find by IBAN.');
            $transactionType = (string) ($array['transaction_type'] ?? null);
            $result          = $this->findByIban((string) $array['iban'], $transactionType);
        }
        if (null !== $result) {
            $return = $result->toArray();
            Log::debug('Result of findByIBAN is not null, returning:', $return);

            return $return;
        }
        // If the IBAN search result is NULL, but the IBAN itself is not null,
        // CSV importer will return an array with the IBAN (and optionally the name).
        // this prevents a situation where the CSV importer


        // find by name, return only if it's an asset or liability account.
        if (isset($array['name']) && '' !== (string) $array['name']) {
            Log::debug('Find by name.');
            $result = $this->findByName((string) $array['name']);
        }
        if (null !== $result) {
            $return = $result->toArray();
            Log::debug('Result of findByName is not null, returning:', $return);

            return $return;
        }

        Log::debug('Found no account or haven\'t searched for one.');

        // append an empty type to the array for consistency's sake.
        $array['type'] = $array['type'] ?? null;
        $array['bic']  = $array['bic'] ?? null;

        // Return ID or name if not null
        if (null !== $array['id'] || '' !== (string)$array['name']) {
            Log::debug('Array with account has some name info, return that.', $array);

            return $array;
        }

        // Return ID or IBAN if not null
        if (null !== $array['id'] || '' !== (string)$array['iban']) {
            Log::debug('Array with account has some IBAN info, return that.', $array);

            return $array;
        }

        // if the default account is not NULL, return that one instead:
        if (null !== $defaultAccount) {
            $default = $defaultAccount->toArray();
            Log::debug('Default account is not null, so will return:', $default);

            return $default;
        }
        Log::debug('Default account is NULL, so will return: ', $array);

        return $array;
    }

    /**
     * @param string $value
     *
     * @return Account|null
     * @throws ImportException
     */
    private function findById(string $value): ?Account
    {
        Log::debug(sprintf('Going to search account with ID "%s"', $value));
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setField('id');
        $request->setQuery($value);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImportException($e->getMessage());
        }
        if (1 === count($response)) {
            /** @var Account $account */
            try {
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImportException($e->getMessage());
            }

            Log::debug(sprintf('[a] Found %s account #%d based on ID "%s"', $account->type, $account->id, $value));

            return $account;
        }

        Log::debug('Found NOTHING.');

        return null;
    }

    /**
     * @param string $iban
     * @param string $transactionType
     *
     * @return Account|null
     * @throws ImportException
     */
    private function findByIban(string $iban, string $transactionType): ?Account
    {
        Log::debug(sprintf('Going to search account with IBAN "%s"', $iban));
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setField('iban');
        $request->setQuery($iban);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImportException($e->getMessage());
        }
        if (0 === count($response)) {
            Log::debug('Found NOTHING.');

            return null;
        }

        if (1 === count($response)) {
            /** @var Account $account */
            try {
                $account = $response->current();
            } catch (ApiException $e) {
                throw new ImportException($e->getMessage());
            }
            // catch impossible combination "expense" with "deposit"
            if ('expense' === $account->type && 'deposit' === $transactionType) {
                Log::debug(
                    sprintf(
                        'Out of cheese error (IBAN). Found Found %s account #%d based on IBAN "%s". But not going to use expense/deposit combi.',
                        $account->type, $account->id, $iban
                    )
                );
                Log::debug('Firefly III will have to make the correct decision.');

                return null;
            }
            Log::debug(sprintf('[a] Found %s account #%d based on IBAN "%s"', $account->type, $account->id, $iban));

            // to fix issue #4293, Firefly III will ignore this account if it's an expense or a revenue account.
            if (in_array($account->type, ['expense', 'revenue'], true)) {
                Log::debug('[a] CSV importer will pretend not to have found anything. Firefly III must handle the IBAN.');

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
     * @param string $name
     *
     * @return Account|null
     * @throws ImportException
     */
    private function findByName(string $name): ?Account
    {
        Log::debug(sprintf('Going to search account with name "%s"', $name));
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetSearchAccountRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setField('name');
        $request->setQuery($name);
        /** @var GetAccountsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImportException($e->getMessage());
        }
        if (0 === count($response)) {
            Log::debug('Found NOTHING.');

            return null;
        }
        /** @var Account $account */
        foreach ($response as $account) {
            if (in_array($account->type, [AccountType::ASSET, AccountType::LOAN, AccountType::DEBT, AccountType::MORTGAGE], true) && strtolower($account->name) === strtolower($name)) {
                Log::debug(sprintf('[b] Found "%s" account #%d based on name "%s"', $account->type, $account->id, $name));

                return $account;
            }
        }
        Log::debug(sprintf('Found %d account(s) searching for "%s" but not going to use them. Firefly III must handle the values.', count($response), $name));

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


}
