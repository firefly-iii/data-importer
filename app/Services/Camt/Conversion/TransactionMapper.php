<?php

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Mapper\GetAccounts;
use App\Services\Shared\Configuration\Configuration;
use Carbon\Carbon;

/**
 * Class TransactionMapper
 */
class TransactionMapper
{
    use GetAccounts;

    private array         $accountIdentificationSuffixes;
    private array         $allAccounts;
    private Configuration $configuration;

    /**
     * @param  Configuration  $configuration
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
     * @param  array  $transactions
     *
     * @return array
     */
    public function map(array $transactions): array
    {
        app('log')->debug('Map all transactions.');
        // TODO download all accounts from Firefly III, we may need them for verification.

        app('log')->debug(sprintf('Now mapping %d transaction(s)', count($transactions)));
        $result = [];
        /** @var array $transaction */
        foreach ($transactions as $transaction) {
            $result[] = $this->mapSingle($transaction);
        }

        return $result;
    }

    /**
     * @param  array  $current
     *
     * @return string
     */
    private function determineTransactionType(array $current): string
    {
        app('log')->debug('Determine transaction type.');
        $directions = ['source', 'destination'];

        foreach ($directions as $direction) {
            $accountType[$direction] = null;
            foreach ($this->accountIdentificationSuffixes as $accountIdentificationSuffix) {
                // try to find destination account
                if (array_key_exists($direction.'_'.$accountIdentificationSuffix, $current)) {
                    $fieldName               = $direction.'_'.$accountIdentificationSuffix;
                    $accountType[$direction] = $this->getAccountType($accountIdentificationSuffix, $current[$fieldName]);
                }
            }
        }

        // TODO catch all cases according lines 281 - 285 and https://docs.firefly-iii.org/firefly-iii/financial-concepts/transactions/#:~:text=In%20Firefly%20III%2C%20a%20transaction,slightly%20different%20from%20one%20another.

        switch (true) {
            case $accountType['source'] === 'asset' && $accountType['destination'] === 'expense' && $current['amount'] < 0: // case on line 369
            case $accountType['source'] === 'asset' && $accountType['destination'] === null && $current['amount'] < 0: // case on line 369
            case $accountType['source'] === 'asset' && $accountType['destination'] === 'revenue'  && $current['amount'] < 0: // there is no expense-account, but the account was found under revenue, so we assume this is a withdrawal with an inexisting expense account
                return "withdrawal";
            case $accountType['source'] === 'asset' && $accountType['destination'] === 'revenue' && $current['amount'] > 0: // case on line 397
            case $accountType['source'] === 'asset' && $accountType['destination'] === null && $current['amount'] > 0: // case on line 397
            case $accountType['source'] === 'asset' && $accountType['destination'] === 'expense': // there is no revenue-account, but the account was found under expense, so we assume this is a deposit with an inexisting revenue account
                return "deposit";
            case $accountType['source'] === 'asset' && $accountType['destination'] === 'asset':
                return "transfer"; // line 382 / 383
            default:
                app('log')->error(
                    sprintf(
                        'Invalid transaction: source = "%s", destination = "%s"',
                        $accountType['source'] ?: '<null>',
                        $accountType['destination'] ?: '<null>'
                    )
                ); // 285
        }
    }

    /**
     * @param $direction
     * @param $current
     * @return string
     */
    private function getAccountId($direction, $current): string
    {
        app('log')->debug('getAccountId');
        foreach ($this->accountIdentificationSuffixes as $accountIdentificationSuffix) {
            $field = $direction.'_'.$accountIdentificationSuffix;
            if (array_key_exists($field, $current)) {
                // there is a value...
                foreach ($this->allAccounts as $account) {
                    // so we check all accounts for a match
                    if ($current[$field] == $account->$accountIdentificationSuffix) {
                        // we have a match
                        app('log')->warning(sprintf('Just mapped account "%s"', $account->id));
                        return (string) $account->id;
                    }
                }
                //app('log')->warning(sprintf('Unable to map an account for "%s"',$current[$field]));
            }
            //app('log')->warning(sprintf('There is no field for "%s" in the transaction',$direction));
        }
        return '';
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
     * @param  array  $current
     * @param  string  $fieldName
     * @param  string  $direction
     * @param  array  $data
     *
     * @return array
     */
    private function mapAccount(array $current, string $fieldName, string $direction, array $data): array
    {
        // bravely assume there's just one value in the array:
        $fieldValue = join('', $data['data']);

        // replace with mapping
        if (array_key_exists($fieldValue, $data['mapping'])) {
            $key           = sprintf('%s_id', $direction);
            $current[$key] = $data['mapping'][$fieldValue];
        }

        // leave original value
        if (!array_key_exists($fieldValue, $data['mapping'])) {
            // $direction is either 'source' or 'destination'
            // $fieldName is 'id', 'iban','name' or 'number'
            $key           = sprintf('%s_%s', $direction, $fieldName);
            $current[$key] = $fieldValue;
        }

        return $current;
    }

    /**
     * @param  mixed  $current
     * @param  string  $type
     * @param  array  $data
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
     * @param  array  $transaction
     *
     * @return array
     */
    private function mapSingle(array $transaction): array
    {
        app('log')->debug('Now mapping single transaction');
        // make a new transaction:
        $result         = [
            //'user'          => 1, // ??
            'group_title'             => null,
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $splits         = $transaction['splits'] ?? 1;
        $group_handling = $this->configuration->getGroupedTransactionHandling();
        app('log')->debug(sprintf('Transaction has %d split(s)', $splits));
        for ($i = 0; $i < $splits; $i++) {
            $split = $transaction['transactions'][$i] ?? false;
            if (false === $split) {
                app('log')->warning(sprintf('No split #%d found, break.', $i));
                continue;
            }
            $current = [
                'type' => 'withdrawal', // perhaps to be overruled later.
            ];
            /**
             * @var string $role
             * @var array $data
             */
            foreach ($split as $role => $data) {
                // actual content of the field is in $data['data'], which is an array
                switch ($role) {
                    default:
                        app('log')->error(sprintf('Cannot handle role "%s" yet.', $role));
                        break;
                    case '_ignore':
                        break;
                    case 'note':
                        // TODO perhaps lift into separate method?
                        $current['notes'] = $current['notes'] ?? '';
                        $addition         = "  \n".join("  \n", $data['data']);
                        $current['notes'] .= $addition;
                        break;
                    case 'date_process':
                        // TODO perhaps lift into separate method?
                        $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
                        $current['process_date'] = $carbon->toIso8601String();
                        break;
                    case 'date_transaction':
                        // TODO perhaps lift into separate method?
                        $carbon          = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
                        $current['date'] = $carbon->toIso8601String();
                        break;
                    case 'date_payment':
                        // TODO perhaps lift into separate method?
                        $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
                        $current['payment_date'] = $carbon->toIso8601String();
                        break;
                    case 'date_book':
                        // TODO perhaps lift into separate method?
                        $carbon               = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
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
                        if ('group' === $group_handling || 'split' === $group_handling) {
                            // use first description
                            $addition = $data['data'][0];
                        }
                        if ('single' === $group_handling) {
                            // just use the last description
                            $addition = end($data['data']);
                        }
                        $current['description'] .= $addition;
                        break;
                    case 'amount':
                        $current['amount'] = null;
                        if ('group' === $group_handling || 'split' === $group_handling) {
                            // if multiple values, use biggest (... at index 0?)
                            foreach ($data['data'] as $amount) {
                                if (abs($current['amount']) < abs($amount) || $current['amount'] == null) {
                                    $current['amount'] = $amount;
                                }
                            }
                        }
                        if ('single' === $group_handling) {
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
            $current = $this->sanityCheck($current);
            if (null === $current) {
                // give warning, skip transaction.
            }
            // TODO loop over $current and clean up if necessary.
            $result['transactions'][] = $current;
        }

        return $result;
    }

    /**
     * A transaction has a bunch of minimal requirements. This method checks if they are met.
     *
     * It will also correct the transaction type (if possible).
     *
     * @param  array  $current
     *
     * @return array|null
     */
    private function sanityCheck(array $current): ?array
    {
        // at this point the source and destination could be set according to the content of the XML.
        // but they could be reversed: in the case of incoming money the "source" is actually the
        // relatedParty / opposing party and not the normal account. So both accounts (if present in the array)
        // need to be validated to see what types they are. This also depends on the amount (positive or negative).

        // not set source_id, iban or name? Then add the backup account
        if (
            !array_key_exists('source_id', $current)
            && !array_key_exists('source_name', $current)
            && !array_key_exists('source_iban', $current)
            && !array_key_exists('source_number', $current)) {
            // TODO add backup account
        }

        $sourceIsNew = false;
        // not set source_id, but others are present? Make sure the account mentioned actually exists.
        // if it does not exist (it is "new"), do nothing for the time being just mark it as such.
        if (
            !array_key_exists('source_id', $current)
            && (array_key_exists('source_name', $current)
                || array_key_exists('source_iban', $current)
                || array_key_exists('source_number', $current))) {
            // the reverse is true: if the info is valid, the source account is not "new".
            $sourceIsNew = !$this->validAccountInfo('source', $current);
        }

        // not set destination? Then add a fake one
        if (
            !array_key_exists('destination_id', $current)
            && !array_key_exists('destination_name', $current)
            && !array_key_exists('destination_iban', $current)
            && !array_key_exists('destination_number', $current)) {
            // TODO add backup account
        }


        // if the source is asset account AND the destination is expense or new AND amount is neg = withdrawal
        // if the source is asset account AND the destination is revenue or new AND amount is pos = deposit
        // if both are transfer AND amount is pos = transfer from dest to source
        // if both are transfer AND amount is neg = transfer from source to dest
        // any other combination is "illegal" and needs a warning.


        // as the source account is not new, we try to map an existing account
        if (!$sourceIsNew) {
            $current['source_id'] = $this->getAccountId('source', $current);
        }
        $current['type'] = $this->determineTransactionType($current);
        // as the destination account is not new, we try to map an existing account
        if ($this->validAccountInfo('destination', $current)) {
            $current['destination_id'] = $this->getAccountId('destination', $current);
        }

        // no amount?
        if (!array_key_exists('amount', $current)) {
            return null;
        }
        // amount must be positive
        if (-1 === bccomp($current['amount'], '0')) {
            // negative amount is debit (or transfer)
            $current['amount'] = bcmul($current['amount'], '-1');
        }
        // if is positive
        if (1 === bccomp($current['amount'], '0')) {
            // positive account is credit (or transfer)
        }

        // no description?
        // no date?

        return $current;
    }

    /**
     * TODO broken.
     * @param  array  $currentTransaction
     * @return array
     */
    private function swapAccounts(array $currentTransaction): array
    {
        $ret       = $currentTransaction;
        $fieldType = ['id', 'iban', 'number', 'name'];

        foreach ($fieldType as $currentFieldType) {
            // move source to destination
            if (array_key_exists('source_'.$currentFieldType, $currentTransaction)) {
                $ret['destination_'.$currentFieldType] = $currentTransaction['source_'.$currentFieldType];
                app('log')->warning('Just replaced destination_'.$currentFieldType.' in $ret');
            }
            if (!array_key_exists('source_'.$currentFieldType, $currentTransaction)) {
                unset($ret['destination_'.$currentFieldType]);
                app('log')->warning('Just DEL destination_'.$currentFieldType.' in $ret');
            }

            // move destination to source
            if (array_key_exists('destination_'.$currentFieldType, $currentTransaction)) {
                $ret['source_'.$currentFieldType] = $currentTransaction['destination_'.$currentFieldType];
                app('log')->warning('Just replaced source_'.$currentFieldType.' in $ret');
            }
            if (!array_key_exists('destination_'.$currentFieldType, $currentTransaction)) {
                app('log')->warning('Just DEL source_'.$currentFieldType.' in $ret');
                unset($ret['source_'.$currentFieldType]);
            }
        }

        return $ret;
    }

    /**
     * @param $fieldName
     * @param $fieldValue
     * @return string|null
     */
    private function getAccountType($fieldName, $fieldValue): ?string
    {
        $accountType = null;
        foreach ($this->allAccounts as $account) {
            if ($account->$fieldName == $fieldValue) {
                $accountType = $account->type;
            }
        }
        return $accountType;
    }

    /**
     * @param  string  $direction
     * @param  array  $current
     *
     * @return bool
     */
    private function validAccountInfo(string $direction, array $current): bool
    {
        // search for existing IBAN
        // search for existing number
        // search for existing name, TODO under which types?
        foreach ($this->accountIdentificationSuffixes as $accountIdentificationSuffix) {
            $field = $direction.'_'.$accountIdentificationSuffix;
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
