<?php

/*
 * TransactionMapper.php
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

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Mapper\GetAccounts;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionMapper
 */
class TransactionMapper
{
    use GetAccounts;
    use ProgressInformation;

    private array         $accountIdentificationSuffixes;
    private array         $allAccounts;

    /**
     * @throws ImporterErrorException
     */
    public function __construct(private Configuration $configuration)
    {
        Log::debug('Constructed TransactionMapper.');
        $this->allAccounts                   = $this->getAllAccounts();
        $this->accountIdentificationSuffixes = ['id', 'iban', 'number', 'name'];
    }

    public function map(array $transactions): array
    {
        Log::debug(sprintf('Now mapping %d transaction(s)', count($transactions)));
        $result = [];
        $total  = count($transactions);

        /** @var array $transaction */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('[%s] [%d/%d] Now mapping.', config('importer.version'), $index + 1, $total));
            $result[] = $this->mapTransactionGroup($transaction);
            Log::debug(sprintf('[%s] [%d/%d] Now done with mapping', config('importer.version'), $index + 1, $total));
        }
        Log::debug(sprintf('Mapped %d transaction(s)', count($result)));

        return $result;
    }

    private function mapTransactionGroup(array $transaction): array
    {
        // make a new transaction:
        $result        = [
            'group_title'                 => null,
            'error_if_duplicate_hash'     => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'                => [],
        ];
        $splits        = $transaction['splits'] ?? 1;
        $groupHandling = $this->configuration->getGroupedTransactionHandling();
        Log::debug(sprintf('Transaction has %d split(s)', $splits));
        for ($i = 0; $i < $splits; ++$i) {
            /** @var array|false $split */
            $split                    = $transaction['transactions'][$i] ?? false;
            if (false === $split) {
                Log::warning(sprintf('No split #%d found, break.', $i));

                continue;
            }
            $rawJournal               = $this->mapTransactionJournal($groupHandling, $split);
            $polishedJournal          = null;
            if (null !== $rawJournal) {
                $polishedJournal = $this->sanityCheck($rawJournal);
            }
            if (null === $polishedJournal) {
                // give warning, skip transaction.
            }
            // TODO loop over $current and clean up if necessary.
            $result['transactions'][] = $polishedJournal;
        }
        Log::debug('Firefly III transaction is', $result);

        return $result;
    }

    private function mapTransactionJournal(string $groupHandling, array $split): array
    {
        $current = [
            'type' => 'withdrawal', // perhaps to be overruled later.
        ];

        /**
         * @var string $role
         * @var array  $data
         */
        foreach ($split as $role => $data) {
            // actual content of the field is in $data['data'], which is an array
            if ('single' === $groupHandling || 'group' === $groupHandling) {
                if (array_key_exists('entryDetailAccounterServiceReference', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryAccounterServiceReference', $data['data'])) {
                        unset($data['data']['entryAccounterServiceReference']);
                        Log::debug('Dropped entryAccounterServiceReference');
                    }
                }
                if (array_key_exists('entryDetailBtcDomainCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcDomainCode', $data['data'])) {
                        unset($data['data']['entryBtcDomainCode']);
                        Log::debug('Dropped entryBtcDomainCode');
                    }
                }
                if (array_key_exists('entryDetailBtcFamilyCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcFamilyCode', $data['data'])) {
                        unset($data['data']['entryBtcFamilyCode']);
                        Log::debug('Dropped entryBtcFamilyCode');
                    }
                }
                if (array_key_exists('entryDetailBtcSubFamilyCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcSubFamilyCode', $data['data'])) {
                        unset($data['data']['entryBtcSubFamilyCode']);
                        Log::debug('Dropped entryBtcSubFamilyCode');
                    }
                }
                if (array_key_exists('entryDetailAmount', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryAmount', $data['data'])) {
                        unset($data['data']['entryAmount']);
                        Log::debug('Dropped entryAmount');
                    }
                }
            }

            switch ($role) {
                default:
                    Log::error(sprintf('Cannot handle role "%s" yet.', $role));

                    break;

                case '_ignore':
                    break;

                case 'note':
                    // TODO perhaps lift into separate method?
                    $current['notes']       ??= '';
                    $addition                = "  \n".implode("  \n", $data['data']);
                    $current['notes'] .= $addition;
                    $current['notes']        = trim($current['notes']);

                    break;

                case 'date_process':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['process_date'] = $carbon->toIso8601String();

                    break;

                case 'date_transaction':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['date']         = $carbon->toIso8601String();

                    break;

                case 'date_payment':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['payment_date'] = $carbon->toIso8601String();

                    break;

                case 'date_book':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['book_date']    = $carbon->toIso8601String();
                    $current['date']         = $carbon->toIso8601String();

                    break;

                case 'account-iban':
                    // could be multiple, could be mapped.
                    $current                 = $this->mapAccount($current, 'iban', 'source', $data);

                    break;

                case 'opposing-iban':
                    // could be multiple, could be mapped.
                    $current                 = $this->mapAccount($current, 'iban', 'destination', $data);

                    break;

                case 'opposing-name':
                    // could be multiple, could be mapped.
                    $current                 = $this->mapAccount($current, 'name', 'destination', $data);

                    break;

                case 'external-id':
                    $addition                = implode(' ', $data['data']);
                    $current['external_id']  = $addition;

                    break;

                case 'description': // TODO think about a config value to use both values from level C and D
                    $current['description'] ??= '';
                    $addition                = '';
                    if ('group' === $groupHandling || 'split' === $groupHandling) {
                        // use first description
                        // TODO use named field?
                        $addition = reset($data['data']);
                    }
                    if ('single' === $groupHandling) {
                        // just use the last description
                        // TODO use named field?
                        $addition = end($data['data']);
                    }
                    $current['description'] .= $addition;
                    Log::debug(sprintf('Description is "%s"', $current['description']));

                    break;

                case 'amount':
                    // #8367 default amount = 0
                    $current['amount']       = 0;
                    Log::debug(sprintf('Start with amount at zero ("%s")', $current['amount']));
                    if ('group' === $groupHandling || 'split' === $groupHandling) {
                        Log::debug(sprintf('Group handling is "%s"', $groupHandling));
                        // if multiple values, use biggest (... at index 0?)
                        // TODO this will never work because $current['amount'] is NULL the first time and abs() can't handle that.
                        foreach ($data['data'] as $amount) {
                            if (null === $amount) {
                                // #8367 skip null values
                                continue;
                            }
                            if (is_string($amount)) {
                                $amount = (float) $amount;
                            }
                            if (abs($current['amount']) < abs($amount)) {
                                Log::debug(sprintf('Amount is now "%s" instead of "%s"', $amount, $current['amount']));
                                $current['amount'] = $amount;
                            }
                        }
                    }
                    if ('single' === $groupHandling) {
                        Log::debug(sprintf('Group handling is "%s"', $groupHandling));
                        // if multiple values, use smallest (... at index 1?)
                        foreach ($data['data'] as $amount) {
                            // #8367 skip null values
                            if (null === $amount) {
                                Log::debug('Amount is NULL, continue.');

                                continue;
                            }
                            if (is_string($amount)) {
                                $amount = (float) $amount;
                            }
                            Log::debug(sprintf('Amount is %s.', var_export($amount, true)));
                            // check for null first, should prevent null pointers in abs()
                            if (abs($current['amount'])
                                < abs($amount)) {
                                Log::debug(sprintf('Amount is now "%s" instead of "%s"', $amount, $current['amount']));
                                $current['amount'] = $amount;
                            }
                        }
                    }
                    if (0 === bccomp('0', (string) $current['amount'])) {
                        Log::debug('Amount is ZERO, set to NULL');
                        $current['amount'] = null;
                    }
                    if (null !== $current['amount'] && 0 !== bccomp('0', (string) $current['amount']) && !is_string($current['amount'])) {
                        Log::debug(sprintf('Amount is %s, turn into string', var_export($current['amount'], true)));
                        $current['amount'] = (string) $current['amount'];
                    }
                    Log::debug(sprintf('Final amount is "%s"', $current['amount']));

                    break;

                case 'currency-code':
                    $current                 = $this->mapCurrency($current, 'currency', $data);

                    break;
            }
        }

        return $current;
    }

    /**
     * This function takes the value in $data['data'], which is for example the account
     * name or the account IBAN. It will check if there is a mapping for this value, mapping for example
     * the value "ShrtBankName" to "Short Bank Name".
     *
     * If there is a mapping the value will be replaced. If there is not, no replacement will take place.
     * Either way, the new value will be placed in the correct place in $current.
     *
     * Example results:
     * source_iban = something
     * destination_number = 12345
     * source_id = 5
     */
    private function mapAccount(array $current, string $fieldName, string $direction, array $data): array
    {
        // bravely assume there's just one value in the array:
        $fieldValue = implode('', $data['data']);

        // replace with mapping, if mapping exists.
        if (array_key_exists($fieldValue, $data['mapping'])) {
            $key           = sprintf('%s_id', $direction);
            $current[$key] = $data['mapping'][$fieldValue];
        }

        // leave original value if no mapping exists.
        if (!array_key_exists($fieldValue, $data['mapping'])) {
            // $direction is either 'source' or 'destination'
            // $fieldName is 'id', 'iban','name' or 'number'
            $key           = sprintf('%s_%s', $direction, $fieldName);
            $current[$key] = $fieldValue;
        }

        return $current;
    }

    private function mapCurrency(mixed $current, string $type, array $data): array
    {
        $code = implode('', $data['data']);
        // replace with mapping
        if (array_key_exists($code, $data['mapping'])) {
            $key           = sprintf('%s_id', $type);
            $current[$key] = $data['mapping'][$code];
        }

        // leave original IBAN
        if (!array_key_exists($code, $data['mapping'])) {
            $key           = sprintf('%s_code', $type);
            $current[$key] = $code;
        }

        return $current;
    }

    /**
     * A transaction has a bunch of minimal requirements. This method checks if they are met.
     *
     * It will also correct the transaction type (if possible).
     */
    private function sanityCheck(array $current): ?array
    {
        Log::debug('Start of sanityCheck');
        // no amount?
        if (!array_key_exists('amount', $current)) {
            Log::error('Array has no amount information, cannot fix.');

            return null;
        }
        if ('' === $current['amount']) {
            Log::error('Array has empty amount information, cannot fix.');

            return null;
        }
        if (null === $current['amount']) {
            Log::error('Array has NULL amount information, cannot fix.');

            return null;
        }

        // if there is no source information, add the default account now:
        if ($this->accountDetailsEmpty('source', $current)) {
            Log::debug('Array has no source information, added default info.');
            $current['source_id'] = $this->configuration->getDefaultAccount();
        }

        // if there is no destination information, add an empty account now:
        $current['destination_is_empty'] = false;
        if ($this->accountDetailsEmpty('destination', $current)) {
            Log::debug('Array has no destination information, added default info.');
            $current['destination_name']     = '(no name)';
            $current['destination_is_empty'] = true;
        }

        // if is positive
        if (1 === bccomp((string) $current['amount'], '0')) {
            Log::debug('Swap accounts because amount is positive');
            // positive account is deposit (or transfer), so swap accounts.
            $current = $this->swapAccounts($current);
        }

        $current                         = $this->determineTransactionType($current);
        Log::debug(sprintf('Transaction type is %s', $current['type']));

        // the type is a withdrawal, but we did not recognize the type of the source account.
        // if that did not succeed we did not FIND the source account, and must fall back
        // on the default account.
        $overruleAccount                 = false;
        if ('withdrawal' === $current['type'] && '' === (string) $current['source_type']) {
            $current['source_id'] = $this->configuration->getDefaultAccount();
            unset($current['source_name'], $current['source_iban']);
            Log::warning(sprintf('Withdrawal, but did not recognize the type of the source account. It will be replaced with the default account (#%d).', $current['source_id']));
            $overruleAccount      = true;
        }
        // same for deposit:
        if ('deposit' === $current['type'] && '' === (string) $current['destination_type']) {
            $current['destination_id'] = $this->configuration->getDefaultAccount();
            unset($current['destination_name'], $current['destination_iban']);
            Log::warning(sprintf('Deposit, but did not recognize the destination account. It will be replaced with the default account (#%d).', $current['destination_id']));
            $overruleAccount           = true;
        }
        // at this point it is possible that either of the two actions above have accidentally
        // set BOTH accounts to be the same one. For example, source_id = 1 and destination_iban = ABC
        // (and they point to the same account). This sanity check must be done again. But not right now.

        // amount must be positive
        if (-1 === bccomp((string) $current['amount'], '0')) {
            // negative amount is debit (or transfer)
            $current['amount'] = bcmul((string) $current['amount'], '-1');
        }

        // no description?
        if (!array_key_exists('description', $current)) {
            Log::warning('Did not find a description in the transaction, added "(no description)"');
            $current['description'] = '(no description)';
        }
        if (array_key_exists('description', $current) && '' === (string) $current['description']) {
            Log::warning('Did not find a description in the transaction, added "(no description)"');
            $current['description'] = '(no description)';
        }

        // no date?
        if (!array_key_exists('date', $current)) {
            Log::warning(sprintf('Did not find a date in the transaction, added "%s"', Carbon::now()->format('Y-m-d')));
            $current['date'] = Carbon::now()->format('Y-m-d');
        }
        if (array_key_exists('date', $current) && '' === (string) $current['date']) {
            Log::warning(sprintf('Did not find a date in the transaction, added "%s"', Carbon::now()->format('Y-m-d')));
            $current['date'] = Carbon::now()->format('Y-m-d');
        }

        // unset var
        unset($current['destination_is_empty'], $current['source_type'], $current['destination_type']);

        return $current;
    }

    private function accountDetailsEmpty(string $direction, array $current): bool
    {
        $noId     = '' === ($current[sprintf('%s_id', $direction)] ?? '');
        $noIban   = '' === ($current[sprintf('%s_iban', $direction)] ?? '');
        $noNumber = '' === ($current[sprintf('%s_number', $direction)] ?? '');
        $noName   = '' === ($current[sprintf('%s_name', $direction)] ?? '');

        return $noId && $noIban && $noNumber && $noName;
    }

    private function swapAccounts(array $currentTransaction): array
    {
        Log::debug('swapAccounts');
        $return = $currentTransaction;

        foreach ($this->accountIdentificationSuffixes as $suffix) {
            $sourceKey   = sprintf('source_%s', $suffix);
            $destKey     = sprintf('destination_%s', $suffix);
            // if source value exists, save it.
            $sourceValue = array_key_exists($sourceKey, $currentTransaction) ? $currentTransaction[$sourceKey] : null;
            // if destination value exists, save it.
            $destValue   = array_key_exists($destKey, $currentTransaction) ? $currentTransaction[$destKey] : null;

            // always unset source value
            Log::debug(sprintf('[1] Unset "%s" with value "%s"', $sourceKey, $sourceValue));
            Log::debug(sprintf('[2] Unset "%s" with value "%s"', $destKey, $destValue));
            unset($return[$sourceKey], $return[$destKey]);

            // set opposite values
            if (null !== $sourceValue) {
                Log::debug(sprintf('[1] Set "%s" to "%s"', $destKey, $sourceValue));
                $return[$destKey] = $sourceValue;
            }
            // a small exception. Do not set the destination name to "no name" if it's set to "no name" because it was empty.
            if (true === $currentTransaction['destination_is_empty']) {
                Log::debug(sprintf('Will not set "%s" to "%s" because destination is empty.', $sourceKey, $destValue));
            }
            if (null !== $destValue && false === $currentTransaction['destination_is_empty']) {
                Log::debug(sprintf('[2] Set "%s" to "%s"', $sourceKey, $destValue));
                $return[$sourceKey] = $destValue;
            }
        }

        return $return;
    }

    private function determineTransactionType(array $current): array
    {
        Log::debug('Determine transaction type.');
        $directions      = ['source', 'destination'];
        $accountType     = [];
        $lessThanZero    = 1 === bccomp('0', (string) $current['amount']);
        Log::debug(sprintf('Amount is "%s", so lessThanZero is %s', $current['amount'], var_export($lessThanZero, true)));

        foreach ($directions as $direction) {
            Log::debug(sprintf('Now working on direction "%s".', $direction));
            $accountType[$direction]  = null;
            $accountTypeKey           = sprintf('%s_type', $direction);
            $current[$accountTypeKey] = '';
            foreach ($this->accountIdentificationSuffixes as $suffix) {
                $key = sprintf('%s_%s', $direction, $suffix);
                Log::debug(sprintf('Now working on key "%s".', $key));
                // try to find the account
                if (array_key_exists($key, $current) && '' !== (string) $current[$key]) {
                    $foundDirection = $this->getAccountType($suffix, (string) $current[$key], $lessThanZero);
                    Log::debug(
                        sprintf('Transaction array has a "%s"-field with value "%s", and its type is "%s".', $key, $current[$key], $foundDirection)
                    );
                    // should this overrule any existing account type? Since we work down from ID,
                    // if it's already known it should not be overruled.
                    if (null === $foundDirection && null !== $accountType[$direction]) {
                        Log::debug(sprintf('Found direction is null, but accountType[%s] is not null, so we skip.', $direction));
                    }
                    if (null !== $foundDirection && null !== $accountType[$direction] && $foundDirection !== $accountType[$direction]) {
                        Log::debug(sprintf('Found direction "%s" overrules accountType[%s] "%s".', $foundDirection, $direction, $accountType[$direction]));
                        $accountType[$direction]  = $foundDirection;
                        $current[$accountTypeKey] = $foundDirection;
                        Log::debug(sprintf('"%s" = "%s"', $accountTypeKey, $foundDirection));
                    }
                    if (null === $accountType[$direction]) {
                        Log::debug(sprintf('accountType[%s] is set to found direction "%s"', $direction, $foundDirection));
                        $accountType[$direction]  = $foundDirection;
                        $current[$accountTypeKey] = $foundDirection;
                        Log::debug(sprintf('"%s" = "%s"', $accountTypeKey, $foundDirection));
                    }
                }
            }
        }

        // TODO catch all cases according lines 281 - 285 and https://docs.firefly-iii.org/fxirefly-iii/financial-concepts/transactions/#:~:text=In%20Firefly%20III%2C%20a%20transaction,slightly%20different%20from%20one%20another.
        $sourceIsNull    = null === $accountType['source'];
        $sourceIsAsset   = 'asset' === $accountType['source'];
        $sourceIsRevenue = 'revenue' === $accountType['source'];
        $destIsAsset     = 'asset' === $accountType['destination'];
        $destIsExpense   = 'expense' === $accountType['destination'];
        $sourceIsExpense = 'expense' === $accountType['source'];
        $destIsRevenue   = 'revenue' === $accountType['destination'];
        $destIsNull      = null === $accountType['destination'];

        switch (true) {
            case $sourceIsAsset && $destIsExpense && $lessThanZero:
            case $sourceIsAsset && $destIsNull && $lessThanZero:
                // there is no expense account, but the account was found under revenue, so we assume this is a withdrawal with a non-existing expense account
            case $sourceIsAsset && $destIsRevenue && $lessThanZero:
                Log::debug('Based on types, this is a withdrawal');
                $current['type']             = 'withdrawal';

                break;

            case $sourceIsAsset && $destIsRevenue && !$lessThanZero:
            case $sourceIsAsset && $destIsNull && !$lessThanZero:
            case $sourceIsNull && $destIsAsset:
            case $sourceIsRevenue && $destIsAsset:
            case $sourceIsAsset && $destIsExpense: // there is no revenue account, but the account was found under expense, so we assume this is a deposit with an non-existing revenue account
                Log::debug('Based on types, this is a deposit');
                $current['type']             = 'deposit';

                break;

            case $sourceIsAsset && $destIsAsset:
                Log::debug('Based on types, this is a transfer');
                $current['type']             = 'transfer'; // line 382 / 383

                break;

            case $sourceIsExpense && $destIsExpense:
                Log::warning('Both types are expense. Impossible. Lets make source_type = "" and type="withdrawal"');
                $current['source_type']      = '';
                $current['type']             = 'withdrawal';

                break;

            case $sourceIsRevenue && $destIsRevenue:
                Log::warning('Both types are revenue. Impossible. Lets make destination_type = "" and type="deposit"');
                $current['destination_type'] = '';
                $current['type']             = 'deposit';

                break;

            case $sourceIsExpense && $destIsNull:
                Log::warning('The source is "expense" but the destination is a new account. Weird! Lets make source_type = "" and type="withdrawal"');
                $current['source_type']      = '';
                $current['type']             = 'withdrawal';

                break;

            case $sourceIsExpense && $destIsAsset:
                Log::warning('The source is "expense" but the destination is an asset. Weird! Lets make source_type = "" and type="deposit"');
                $current['source_type']      = '';
                $current['type']             = 'deposit';

                break;

            default:
                Log::error(
                    sprintf(
                        'Unknown transaction type: source = "%s", destination = "%s". Fall back to "withdrawal"',
                        null !== $accountType['source'] && '' !== $accountType['source'] && '0' !== $accountType['source'] ? $accountType['source'] : null,
                        null !== $accountType['destination'] && '' !== $accountType['destination'] && '0' !== $accountType['destination'] ? $accountType['destination'] : null
                    )
                );                               // 285
                $current['type']             = 'withdrawal'; // line 382 / 383

                break;
        }

        // default back to withdrawal.
        return $current;
    }

    private function getAccountType(string $field, string $value, bool $lessThanZero): ?string
    {
        $count    = 0;
        $result   = null;
        $hitField = null; // the field on which we found a match.
        foreach ($this->allAccounts as $account) {
            // we have a match!
            if ((string) $account->{$field} === (string) $value) {
                // never found a match before!
                if (0 === $count) {
                    Log::debug(sprintf('Recognized "%s" as a "%s"-account by its "%s".', $value, $account->type, $field));
                    $result   = $account->type;
                    $hitField = $field;
                    ++$count;
                }
                // we found a match before, and it's different too.
                if (0 !== $count && $account->type !== $result) {
                    Log::warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    // the previous result always trumps the current result because the order of accountIdentificationSuffixes
                    Log::debug(sprintf('System will keep the previous match and assume account with %s "%s" is a "%s" account', $field, $value, $result));
                    ++$count;
                }
                // we found a match before and it's different. But the data importer has found both "revenue" AND "expense" accounts. What to do?
                $set = [$account->type, $result];
                if (0 !== $count && $account->type !== $result && in_array('revenue', $set, true) && in_array('expense', $set, true) && $lessThanZero) {
                    Log::warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    Log::debug('Because amount is less than zero, we assume "expense" is the correct type.');
                    $result = 'expense';

                    ++$count;
                }
                // we found a match before and it's different. But: previous result was "expense", current result is "revenue"
                if (0 !== $count && $account->type !== $result && in_array('revenue', $set, true) && in_array('expense', $set, true) && !$lessThanZero) {
                    Log::warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    Log::debug('Because amount is more than zero, we assume "revenue" is the correct type.');
                    $result = 'revenue';

                    ++$count;
                }
            }
        }
        if (null === $result) {
            Log::debug(sprintf('Unable to recognize the account type of "%s" "%s", or skipped because unsure.', $field, $value));
        }

        return $result;
    }

    private function getAccountId($direction, $current): string
    {
        Log::debug('getAccountId');
        foreach ($this->accountIdentificationSuffixes as $suffix) {
            $field = sprintf('%s_%s', $direction, $suffix);
            if (array_key_exists($field, $current)) {
                // there is a value...
                foreach ($this->allAccounts as $account) {
                    // so we check all accounts for a match
                    if ($current[$field] === $account->{$suffix}) {
                        // we have a match

                        // only select accounts that are suitable for the type of transaction
                        if ($current['amount'] > 0) {
                            // seems a deposit or transfer
                            if (in_array($account->type, ['asset', 'revenue'], true)) {
                                return (string) $account->id;
                            }
                        }

                        if ($current['amount'] < 0) {
                            // seems a withtrawal or transfer
                            if (in_array($account->type, ['asset', 'expense'], true)) {
                                return (string) $account->id;
                            }
                        }
                        Log::warning(sprintf('Just mapped account "%s" (%s)', $account->id, $account->type));

                        return (string) $account->id;
                    }
                }
                // Log::warning(sprintf('Unable to map an account for "%s"',$current[$field]));
            }
            // Log::warning(sprintf('There is no field for "%s" in the transaction',$direction));
        }

        return '';
    }

    private function validAccountInfo(string $direction, array $current): bool
    {
        // search for existing IBAN
        // search for existing number
        // search for existing name, TODO under which types?
        foreach ($this->accountIdentificationSuffixes as $accountIdentificationSuffix) {
            $field = sprintf('%s_%s', $direction, $accountIdentificationSuffix);
            if (array_key_exists($field, $current)) {
                // there is a value...
                // so we check all accounts for a match
                if (array_any($this->allAccounts, fn ($account) => $current[$field] === $account->{$accountIdentificationSuffix})) {
                    return true;
                }
            }
        }

        return false;
    }
}
