<?php

/*
 * TransactionConverter.php
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
use App\Services\Camt\AbstractTransaction;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Log;

class TransactionConverter
{
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

        /** @var AbstractTransaction $transaction */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('[%s] [%d/%d] Now working on transaction.', config('importer.version'), $index + 1, $total));
            $result[] = $this->convertSingle($transaction);
            Log::debug(sprintf('[%s] [%d/%d] Now done with transaction.', config('importer.version'), $index + 1, $total));
        }
        Log::debug(sprintf('Done converting all %d transactions into pseudo-transactions.', $total));

        return $result;
    }

    /**
     * @throws ImporterErrorException
     */
    private function convertSingle(AbstractTransaction $transaction): array
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
                $field = (string)$field;
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
