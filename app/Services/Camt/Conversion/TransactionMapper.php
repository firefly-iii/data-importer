<?php

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use Carbon\Carbon;

/**
 * Class TransactionMapper
 */
class TransactionMapper
{
    private Configuration $configuration;

    /**
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
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
        foreach ($transactions as $transaction) {
            $result[] = $this->mapSingle($transaction);
        }

        return $result;
    }

    /**
     * @param array $transaction
     *
     * @return array
     */
    private function mapSingle(array $transaction): array
    {
        app('log')->debug(sprintf('Now mapping single transaction'));
        // make a new transaction:
        $result = [
            //'user'          => 1, // ??
            'group_title'             => null,
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $splits = $transaction['splits'] ?? 1;
        app('log')->debug(sprintf('Transaction has %d split(s)', $splits));
        for ($i = 0; $i < $splits; $i++) {
            $split = $transaction['transactions'][$i] ?? false;
            if (false === $split) {
                app('log')->warning(sprintf('No split #%d found, break.', $i));
                continue;
            }
            $current = [];
            /**
             * @var string $role
             * @var array  $data
             */
            foreach ($split as $role => $data) {
                // actual content of the field is in $data['data'], which is an array
                switch ($role) {
                    default:
                        // temp debug exit message:
                        echo sprintf('Cannot handle role "%s".', $role);
                        echo PHP_EOL;
                        exit;
                        // end of temp debug exit message
                        throw new ImporterErrorException(sprintf('Cannot handle role "%s".', $role));
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
                        $carbon                  = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
                        $current['book_date'] = $carbon->toIso8601String();
                        break;

                    case 'account-iban':
                        // could be multiple, could be mapped.
                        $current = $this->mapAccount($current, 'source', $data);
                        break;
                    case 'opposing-iban':
                        // could be multiple, could be mapped.
                        $current = $this->mapAccount($current, 'destination', $data);
                        break;

                    case 'external-id':
                        $addition               = join(' ', $data['data']);
                        $current['external_id'] = $addition;
                        break;
                    case 'description':
                        $current['description'] = $current['description'] ?? '';
                        $addition               = join(' ', $data['data']);
                        $current['description'] .= $addition;
                        break;
                    case 'amount':
                        $current['amount'] = $data['data'][0];
                        break;
                    case 'currency-code':
                        $current = $this->mapCurrency($current, 'currency', $data);
                        break;

                }
            }
            // TODO loop over $current and clean up if necessary.
            $result['transactions'][] = $current;

        }

        return $result;
    }

    /**
     * @param mixed  $current
     * @param string $direction
     * @param array  $data
     *
     * @return array
     */
    private function mapAccount(mixed $current, string $direction, array $data): array
    {
        // bravely assume there's just one IBAN in the array:
        $iban = join('', $data['data']);

        // replace with mapping
        if (array_key_exists($iban, $data['mapping'])) {
            $key           = sprintf('%s_id', $direction);
            $current[$key] = $data['mapping'][$iban];
        }

        // leave original IBAN
        if (!array_key_exists($iban, $data['mapping'])) {
            $key           = sprintf('%s_iban', $direction);
            $current[$key] = $iban;
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


}
