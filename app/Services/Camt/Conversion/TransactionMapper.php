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
        for($i=0;$i<$splits;$i++) {
            $split = $transaction['transactions'][$i] ?? false;
            if(false === $split) {
                app('log')->warning(sprintf('No split #%d found, break.', $i));
                continue;
            }
            $current = [];
            foreach($split as $role => $data) {
                // actual content of the field is in $data['data'], which is an array
                switch($role) {
                    default:
                        // temp debug exit message:
                        echo sprintf('Cannot handle role "%s".', $role);
                        echo PHP_EOL;
                        exit;
                        // end of temp debug exit message
                        throw new ImporterErrorException(sprintf('Cannot handle role "%s".', $role));
                    case 'note':
                        $current['notes'] = join(' ', $data['data']);
                        break;
                    case 'date_process':
                        $carbon = Carbon::createFromFormat('Y-m-d H:i:s', $data['data'][0]);
                        $current['process_date'] = $carbon->toIso8601String();
                    case 'account-iban':
                        // could be multiple, could be mapped.
                        $current = $this->mapAccount($current, 'source', $data);
                }
            }
            $result['transactions'][] = $current;

        }

        return $result;
    }


}
