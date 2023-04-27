<?php

namespace App\Services\Camt\Conversion;

use App\Services\Camt\Transaction;
use App\Services\Shared\Configuration\Configuration;

class TransactionConverter
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
    public function convert(array $transactions): array
    {
        $result = [];
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $result[] = $this->convertSingle($transaction);
        }

        return $result;
    }

    /**
     * @param Transaction $transaction
     *
     * @return array
     */
    private function convertSingle(Transaction $transaction): array
    {
        $result = [
            'group_title'             => null,
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $count  = $transaction->countSplits();
        $count  = 0 === $count ? 1 : $count; // add at least one transaction:
        for ($i = 0; $i < $count; $i++) {
            // add a transaction:
            $current                  = [
                'type'             => 'unknown',
                'date'             => $transaction->getDate($i),
                'currency_code'    => $transaction->getCurrencyCode($i),
                'amount'           => $transaction,
                // TODO the rest + accounts, budgets, etc.
                'description'      => null,
                'source_id'        => null,
                'source_name'      => null,
                'destination_id'   => null,
                'destination_name' => null,
                'tags_comma'       => [],
                'tags_space'       => [],
            ];
            $result['transactions'][] = $current;
        }

        return $result;
    }

}
