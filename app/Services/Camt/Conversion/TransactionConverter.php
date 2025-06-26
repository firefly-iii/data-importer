<?php

declare(strict_types=1);

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\Camt\Transaction;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Illuminate\Support\Facades\Log;

class TransactionConverter
{
    use ProgressInformation;

    public function __construct(private Configuration $configuration)
    {
        Log::debug('Constructed TransactionConverter.');
    }

    /**
     * @throws ImporterErrorException
     */
    public function convert(array $transactions): array
    {
        $total  = count($transactions);
        Log::debug(sprintf('Convert all %d transactions into pseudo-transactions.', $total));
        $result = [];

        /** @var Transaction $transaction */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('[%d/%d] Now working on transaction.', $index + 1, $total));
            $result[] = $this->convertSingle($transaction);
            Log::debug(sprintf('[%d/%d] Now done with transaction.', $index + 1, $total));
        }
        Log::debug(sprintf('Done converting all %d transactions into pseudo-transactions.', $total));

        return $result;
    }

    /**
     * @throws ImporterErrorException
     */
    private function convertSingle(Transaction $transaction): array
    {
        Log::debug('Convert single transaction into pseudo-transaction.');
        $result           = [
            'transactions' => [],
        ];
        $configuredRoles  = $this->getConfiguredRoles();
        $mapping          = $this->configuration->getMapping();
        $allRoles         = $this->configuration->getRoles();
        $count            = $transaction->countSplits();
        $count            = 0 === $count ? 1 : $count; // add at least one transaction inside the Transaction.
        $fieldNames       = array_keys(config('camt.fields'));
        $result['splits'] = $count;

        for ($i = 0; $i < $count; ++$i) {
            // loop all available roles, see if they're configured and if so, get the associated field from the transaction.
            // some roles can be configured multiple times, so the $current array may hold multiple values.
            // the final response to this may be to join these fields or only use the last one.
            $current                  = [];
            foreach ($fieldNames as $field) {
                $role  = $allRoles[$field] ?? '_ignore';
                if ('_ignore' !== $role) {
                    Log::debug(sprintf('Field "%s" was given role "%s".', $field, $role));
                }
                if ('_ignore' === $role) {
                    Log::debug(sprintf('Field "%s" is ignored!', $field));
                }
                // get by index, so grab it from the appropriate split or get the first one.
                $value = trim($transaction->getFieldByIndex($field, $i));
                if ('' !== $value) {
                    $current[$role] ??= [
                        'data'    => [],
                        'mapping' => [],
                    ];
                    if (array_key_exists($field, $mapping)) {
                        $current[$role]['mapping'] = array_merge($mapping[$field], $current[$role]['mapping']);
                    }
                    $current[$role]['data'][$field] = $value;
                    $current[$role]['data']         = array_unique($current[$role]['data']);
                }
            }
            $result['transactions'][] = $current;
        }
        Log::debug(sprintf('Pseudo-transaction is: %s', json_encode($result)));

        return $result;
    }

    private function getConfiguredRoles(): array
    {
        return array_unique(array_values($this->configuration->getRoles()));
    }
}
