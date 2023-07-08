<?php

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Mapper\GetAccounts;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Carbon\Carbon;

/**
 * Class TransactionMapper
 */
class TransactionMapper
{
    use GetAccounts;
    use ProgressInformation;
    private array         $accountIdentificationSuffixes;
    private array         $allAccounts;
    private Configuration $configuration;

    /**
     * @param Configuration $configuration
     * @throws ImporterErrorException
     */
    public function __construct(Configuration $configuration)
    {
        app('log')->debug('Constructed TransactionMapper.');
        $this->configuration                 = $configuration;
        $this->allAccounts                   = $this->getAllAccounts();
        $this->accountIdentificationSuffixes = ['id', 'iban', 'number', 'name'];
    }

    /**
     * @param array $transactions
     *
     * @return array
     */
    public function map(array $transactions): array
    {
        app('log')->debug(sprintf('Now mapping %d transaction(s)', count($transactions)));
        $result = [];
        /** @var array $transaction */
        foreach ($transactions as $index => $transaction) {
            app('log')->debug(sprintf('Now mapping index #%d', $index));
            $result[] = $this->mapTransactionGroup($transaction);
            app('log')->debug(sprintf('Now done with mapping index #%d', $index));
        }
        app('log')->debug(sprintf('Mapped %d transaction(s)', count($result)));
        return $result;
    }

    /**
     * @param string $direction
     * @param array  $current
     * @return bool
     */
    private function accountDetailsEmpty(string $direction, array $current): bool
    {
        $noId     = '' === ($current[sprintf('%s_id', $direction)] ?? '');
        $noIban   = '' === ($current[sprintf('%s_iban', $direction)] ?? '');
        $noNumber = '' === ($current[sprintf('%s_number', $direction)] ?? '');
        $noName   = '' === ($current[sprintf('%s_name', $direction)] ?? '');
        return $noId && $noIban && $noNumber && $noName;
    }

    /**
     * @param array $current
     *
     * @return string
     */
    private function determineTransactionType(array $current): string
    {
        app('log')->debug('Determine transaction type.');
        $directions   = ['source', 'destination'];
        $accountType  = [];
        $lessThanZero = 1 === bccomp('0', $current['amount']);
        app('log')->debug(sprintf('Amount is "%s", so lessThanZero is %s', $current['amount'], var_export($lessThanZero, true)));

        foreach ($directions as $direction) {
            app('log')->debug(sprintf('Now working on direction "%s".', $direction));
            $accountType[$direction] = null;
            foreach ($this->accountIdentificationSuffixes as $suffix) {
                $key = sprintf('%s_%s', $direction, $suffix);
                app('log')->debug(sprintf('Now working on key "%s".', $key));
                // try to find the account
                if (array_key_exists($key, $current) && '' !== (string)$current[$key]) {
                    $foundDirection = $this->getAccountType($suffix, $current[$key], $lessThanZero);
                    app('log')->debug(
                        sprintf('Transaction array has a "%s"-field with value "%s", and its type is "%s".', $key, $current[$key], $foundDirection)
                    );
                    // should this overrule any existing account type? Since we work down from ID,
                    // if it's already known it should not be overruled.
                    if(null === $foundDirection && null !== $accountType[$direction]){
                        app('log')->debug(sprintf('Found direction is null, but accountType[%s] is not null, so we skip.', $direction));
                    }
                    if(null !== $foundDirection && null !== $accountType[$direction] && $foundDirection !== $accountType[$direction]) {
                        app('log')->debug(sprintf('Found direction "%s" overrules accountType[%s] "%".', $foundDirection, $direction, $accountType[$direction]));
                        $accountType[$direction] = $foundDirection;
                    }
                    if(null === $accountType[$direction]) {
                        app('log')->debug(sprintf('accountType[%s] is set to found direction "%s"', $direction, $foundDirection));
                        $accountType[$direction] = $foundDirection;
                    }
                }
            }
        }

        // TODO catch all cases according lines 281 - 285 and https://docs.firefly-iii.org/firefly-iii/financial-concepts/transactions/#:~:text=In%20Firefly%20III%2C%20a%20transaction,slightly%20different%20from%20one%20another.
        $sourceIsNull    = null === $accountType['source'];
        $sourceIsAsset   = 'asset' === $accountType['source'];
        $sourceIsRevenue = 'revenue' === $accountType['source'];
        $destIsAsset     = 'asset' === $accountType['destination'];
        $destIsExpense   = 'expense' === $accountType['destination'];
        $destIsRevenue   = 'revenue' === $accountType['destination'];
        $destIsNull      = null === $accountType['destination'];
        switch (true) {
            case $sourceIsAsset && $destIsExpense && $lessThanZero:
            case $sourceIsAsset && $destIsNull && $lessThanZero:
                // there is no expense account, but the account was found under revenue, so we assume this is a withdrawal with a non-existing expense account
            case $sourceIsAsset && $destIsRevenue && $lessThanZero:
                return 'withdrawal';
            case $sourceIsAsset && $destIsRevenue && !$lessThanZero:
            case $sourceIsAsset && $destIsNull && !$lessThanZero:
            case $sourceIsNull && $destIsAsset:
            case $sourceIsRevenue && $destIsAsset:
            case $sourceIsAsset && $destIsExpense: // there is no revenue account, but the account was found under expense, so we assume this is a deposit with an non-existing revenue account
                return 'deposit';
            case $sourceIsAsset && $destIsAsset:
                return 'transfer'; // line 382 / 383

            default:
                app('log')->error(
                    sprintf(
                        'Unknown transaction type: source = "%s", destination = "%s"',
                        $accountType['source'] ?: null,
                        $accountType['destination'] ?: null
                    )
                ); // 285
        }


        // default back to withdrawal.
        return 'withdrawal';
    }

    /**
     * @param $direction
     * @param $current
     * @return string
     */
    private function getAccountId($direction, $current): string
    {
        app('log')->debug('getAccountId');
        foreach ($this->accountIdentificationSuffixes as $suffix) {
            $field = sprintf('%s_%s', $direction, $suffix);
            if (array_key_exists($field, $current)) {
                // there is a value...
                foreach ($this->allAccounts as $account) {
                    // so we check all accounts for a match
                    if ($current[$field] === $account->$suffix) {
                        // we have a match

                        // only select accounts that are suitable for the type of transaction
                        if ($current['amount'] > 0) {
                            // seems a deposit or transfer
                            if (in_array($account->type, ['asset', 'revenue'], true)) {
                                return (string)$account->id;
                            }
                        }

                        if ($current['amount'] < 0) {
                            // seems a withtrawal or transfer
                            if (in_array($account->type, ['asset', 'expense'], true)) {
                                return (string)$account->id;
                            }
                        }
                        app('log')->warning(sprintf('Just mapped account "%s" (%s)', $account->id, $account->type));
                        return (string)$account->id;
                    }
                }
                //app('log')->warning(sprintf('Unable to map an account for "%s"',$current[$field]));
            }
            //app('log')->warning(sprintf('There is no field for "%s" in the transaction',$direction));
        }
        return '';
    }

    /**
     * @param string $field
     * @param string $value
     * @param bool $lessThanZero
     * @return string|null
     */
    private function getAccountType(string $field, string $value, bool $lessThanZero): ?string
    {
        $count    = 0;
        $result   = null;
        $hitField = null; // the field on which we found a match.
        foreach ($this->allAccounts as $account) {
            // we have a match!
            if ((string)$account->$field === (string)$value) {
                // never found a match before!
                if (0 === $count) {
                    app('log')->debug(sprintf('Recognized "%s" as a "%s"-account by its "%s".', $value, $account->type, $field));
                    $result   = $account->type;
                    $hitField = $field;
                    $count++;
                }
                // we found a match before, and it's different too.
                if (0 !== $count && $account->type !== $result) {
                    app('log')->warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    // the previous result always trumps the current result because the order of accountIdentificationSuffixes
                    app('log')->debug(sprintf('System will keep the previous match and assume account with %s "%s" is a "%s" account', $field, $value, $result));
                    $count++;
                }
                // we found a match before and it's different. But the data importer has found both "revenue" AND "expense" accounts. What to do?
                $set = [$account->type, $result];
                if (0 !== $count && $account->type !== $result && in_array('revenue', $set, true) && in_array('expense', $set, true) && $lessThanZero) {
                    app('log')->warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    app('log')->debug('Because amount is less than zero, we assume "expense" is the correct type.');
                    $result = 'expense';

                    $count++;
                }
                // we found a match before and it's different. But: previous result was "expense", current result is "revenue"
                if (0 !== $count && $account->type !== $result && in_array('revenue', $set, true) && in_array('expense', $set, true) && !$lessThanZero) {
                    app('log')->warning(sprintf('Recognized "%s" as a "%s"-account (on the "%s"-field) but ALSO as a "%s"-account (previous match was on the "%s"-field)!', $value, $result, $field, $account->type, $hitField));
                    app('log')->debug('Because amount is more than zero, we assume "revenue" is the correct type.');
                    $result = 'revenue';

                    $count++;
                }
            }
        }
        if (null === $result) {
            app('log')->debug(sprintf('Unable to recognize the account type of "%s" "%s", or skipped because unsure.', $field, $value));
        }
        return $result;
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
     *
     * @param array  $current
     * @param string $fieldName
     * @param string $direction
     * @param array  $data
     *
     * @return array
     */
    private function mapAccount(array $current, string $fieldName, string $direction, array $data): array
    {
        // bravely assume there's just one value in the array:
        $fieldValue = join('', $data['data']);

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

    /**
     * @param mixed  $current
     * @param string $type
     * @param array  $data
     *
     * @return array
     */
    private function mapCurrency(mixed $current, string $type, array $data): array
    {
        $code = join('', $data['data']);
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
     * @param array $transaction
     *
     * @return array
     */
    private function mapTransactionGroup(array $transaction): array
    {
        // make a new transaction:
        $result        = [
            'group_title'             => null,
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $splits        = $transaction['splits'] ?? 1;
        $groupHandling = $this->configuration->getGroupedTransactionHandling();
        app('log')->debug(sprintf('Transaction has %d split(s)', $splits));
        for ($i = 0; $i < $splits; $i++) {
            /** @var array|bool $split */
            $split = $transaction['transactions'][$i] ?? false;
            if (is_bool($split) && false === $split) {
                app('log')->warning(sprintf('No split #%d found, break.', $i));
                continue;
            }
            $rawJournal      = $this->mapTransactionJournal($groupHandling, $split);
            $polishedJournal = null;
            if (null !== $rawJournal) {
                $polishedJournal = $this->sanityCheck($rawJournal);
            }
            if (null === $polishedJournal) {
                // give warning, skip transaction.
            }
            // TODO loop over $current and clean up if necessary.
            $result['transactions'][] = $polishedJournal;
        }

        return $result;
    }

    /**
     * @param string $groupHandling
     * @param array  $split
     * @return array|null
     */
    private function mapTransactionJournal(string $groupHandling, array $split): ?array
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
            if ('single' === $groupHandling or 'group' === $groupHandling) {
                if (array_key_exists('entryDetailAccounterServiceReference', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryAccounterServiceReference', $data['data'])) {
                        unset($data['data']['entryAccounterServiceReference']);
                        app('log')->debug('Dropped entryAccounterServiceReference');
                    }
                }
                if (array_key_exists('entryDetailBtcDomainCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcDomainCode', $data['data'])) {
                        unset($data['data']['entryBtcDomainCode']);
                        app('log')->debug('Dropped entryBtcDomainCode');
                    }
                }
                if (array_key_exists('entryDetailBtcFamilyCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcFamilyCode', $data['data'])) {
                        unset($data['data']['entryBtcFamilyCode']);
                        app('log')->debug('Dropped entryBtcFamilyCode');
                    }
                }
                if (array_key_exists('entryDetailBtcSubFamilyCode', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryBtcSubFamilyCode', $data['data'])) {
                        unset($data['data']['entryBtcSubFamilyCode']);
                        app('log')->debug('Dropped entryBtcSubFamilyCode');
                    }
                }
                if (array_key_exists('entryDetailAmount', $data['data'])) {
                    // we'll use this one, no exception. so the one from level-c can be dropped (if available)
                    if (array_key_exists('entryAmount', $data['data'])) {
                        unset($data['data']['entryAmount']);
                        app('log')->debug('Dropped entryAmount');
                    }
                }
            }
            switch ($role) {
                default:
                    app('log')->error(sprintf('Cannot handle role "%s" yet.', $role));
                    break;
                case '_ignore':
                    break;
                case 'note':
                    // TODO perhaps lift into separate method?
                    $current['notes'] = $current['notes'] ?? '';
                    $addition         = "  \n" . join("  \n", $data['data']);
                    $current['notes'] .= $addition;
                    break;
                case 'date_process':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['process_date'] = $carbon->toIso8601String();
                    break;
                case 'date_transaction':
                    // TODO perhaps lift into separate method?
                    $carbon          = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['date'] = $carbon->toIso8601String();
                    break;
                case 'date_payment':
                    // TODO perhaps lift into separate method?
                    $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['payment_date'] = $carbon->toIso8601String();
                    break;
                case 'date_book':
                    // TODO perhaps lift into separate method?
                    $carbon               = Carbon::createFromFormat('Y-m-d H:i:s', reset($data['data']));
                    $current['book_date'] = $carbon->toIso8601String();
                    $current['date']      = $carbon->toIso8601String();
                    break;
                case 'account-iban':
                    // could be multiple, could be mapped.
                    $current = $this->mapAccount($current, 'iban', 'source', $data);
                    break;
                case 'opposing-iban':
                    // could be multiple, could be mapped.
                    $current = $this->mapAccount($current, 'iban', 'destination', $data);
                    break;
                case 'opposing-name':
                    // could be multiple, could be mapped.
                    $current = $this->mapAccount($current, 'name', 'destination', $data);
                    break;
                case 'external-id':
                    $addition               = join(' ', $data['data']);
                    $current['external_id'] = $addition;
                    break;
                case 'description': // TODO think about a config value to use both values from level C and D
                    $current['description'] = $current['description'] ?? '';
                    $addition               = '';
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
                    app('log')->debug(sprintf('Description is "%s"', $current['description']));
                    break;
                case 'amount':
                    $current['amount'] = null;
                    if ('group' === $groupHandling || 'split' === $groupHandling) {
                        // if multiple values, use biggest (... at index 0?)
                        // TODO this will never work because $current['amount'] is NULL the first time and abs() can't handle that.
                        foreach ($data['data'] as $amount) {
                            if (abs($current['amount']) < abs($amount) || $current['amount'] == null) {
                                $current['amount'] = $amount;
                            }
                        }
                    }
                    if ('single' === $groupHandling) {
                        // if multiple values, use smallest (... at index 1?)
                        foreach ($data['data'] as $amount) {
                            if (abs($current['amount']) > abs($amount) || $current['amount'] == null) {
                                $current['amount'] = $amount;
                            }
                        }
                    }
                    break;
                case 'currency-code':
                    $current = $this->mapCurrency($current, 'currency', $data);
                    break;
            }
        }
        return $current;
    }

    /**
     * A transaction has a bunch of minimal requirements. This method checks if they are met.
     *
     * It will also correct the transaction type (if possible).
     *
     * @param array $current
     *
     * @return array|null
     */
    private function sanityCheck(array $current): ?array
    {
        app('log')->debug('Start of sanityCheck');
        // no amount?
        if (!array_key_exists('amount', $current)) {
            return null;
        }

        // if there is no source information, add the default account now:
        if ($this->accountDetailsEmpty('source', $current)) {
            app('log')->debug('Array has no source information, added default info.');
            $current['source_id'] = $this->configuration->getDefaultAccount();
        }

        // if there is no destination information, add an empty account now:
        if ($this->accountDetailsEmpty('destination', $current)) {
            app('log')->debug('Array has no destination information, added default info.');
            $current['destination_name'] = '(no name)';
        }

        // if is positive
        if (1 === bccomp($current['amount'], '0')) {
            app('log')->debug('Swap accounts because amount is positive');
            // positive account is deposit (or transfer), so swap accounts.
            $current = $this->swapAccounts($current);
        }


        $current['type'] = $this->determineTransactionType($current);
        app('log')->debug(sprintf('Transaction type is %s', $current['type']));
        // need a catch here to invert.


        // as the destination account is not new, we try to map an existing account
        if ($this->validAccountInfo('destination', $current)) {
            //$current['destination_id'] = $this->getAccountId('destination', $current);
        }

        // no amount?
        if (!array_key_exists('amount', $current)) {
            return null;
        }

        // if is positive
        if (1 === bccomp($current['amount'], '0')) {
            // TODO remove this empty if statement.
            // positive account is credit (or transfer)
        }

        // amount must be positive
        if (-1 === bccomp($current['amount'], '0')) {
            // negative amount is debit (or transfer)
            $current['amount'] = bcmul($current['amount'], '-1');
        }

        // no description?
        // no date?

        return $current;
    }

    /**
     * @param array $currentTransaction
     * @return array
     */
    private function swapAccounts(array $currentTransaction): array
    {
        app('log')->debug('swapAccounts');
        $return = $currentTransaction;

        foreach ($this->accountIdentificationSuffixes as $suffix) {
            $sourceKey = sprintf('source_%s', $suffix);
            $destKey   = sprintf('destination_%s', $suffix);
            // if source value exists, save it.
            $sourceValue = array_key_exists($sourceKey, $currentTransaction) ? $currentTransaction[$sourceKey] : null;
            // if destination value exists, save it.
            $destValue = array_key_exists($destKey, $currentTransaction) ? $currentTransaction[$destKey] : null;

            // always unset source value
            app('log')->debug(sprintf('Unset "%s" with value "%s"', $sourceKey, $sourceValue));
            app('log')->debug(sprintf('Unset "%s" with value "%s"', $destKey, $destValue));
            unset($return[$sourceKey], $return[$destKey]);

            // set opposite values
            if (null !== $sourceValue) {
                app('log')->debug(sprintf('Set "%s" to "%s"', $destKey, $sourceValue));
                $return[$destKey] = $sourceValue;
            }
            if (null !== $destValue) {
                app('log')->debug(sprintf('Set "%s" to "%s"', $sourceKey, $destValue));
                $return[$sourceKey] = $destValue;
            }
        }
        return $return;
    }

    /**
     * @param string $direction
     * @param array  $current
     *
     * @return bool
     */
    private function validAccountInfo(string $direction, array $current): bool
    {
        // search for existing IBAN
        // search for existing number
        // search for existing name, TODO under which types?
        foreach ($this->accountIdentificationSuffixes as $accountIdentificationSuffix) {
            $field = sprintf('%s_%s', $direction, $accountIdentificationSuffix);
            if (array_key_exists($field, $current)) {
                // there is a value...
                foreach ($this->allAccounts as $account) {
                    // so we check all accounts for a match
                    if ($current[$field] == $account->$accountIdentificationSuffix) {
                        // we have a match
                        return true;
                    }
                }
            }
        }
        return false;
    }

}
