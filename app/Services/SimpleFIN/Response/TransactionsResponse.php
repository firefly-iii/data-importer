<?php

/*
 * TransactionsResponse.php
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

namespace App\Services\SimpleFIN\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Class TransactionsResponse
 */
class TransactionsResponse extends SimpleFINResponse
{
    private array $transactions = [];

    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);
        $this->parseTransactions();
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getTransactionCount(): int
    {
        return count($this->transactions);
    }

    public function hasTransactions(): bool
    {
        return !empty($this->transactions);
    }

    private function parseTransactions(): void
    {
        $data = $this->getData();

        if (empty($data)) {
            app('log')->warning('SimpleFIN TransactionsResponse: No data to parse');

            return;
        }

        // SimpleFIN API returns transactions in the 'transactions' array within accounts
        if (isset($data['accounts']) && is_array($data['accounts'])) {
            $transactions = [];
            foreach ($data['accounts'] as $account) {
                if (isset($account['transactions']) && is_array($account['transactions'])) {
                    $transactions = array_merge($transactions, $account['transactions']);
                }
            }
            $this->transactions = $transactions;
            app('log')->debug(sprintf('SimpleFIN TransactionsResponse: Parsed %d transactions', count($this->transactions)));
            return;
        }
        app('log')->warning('SimpleFIN TransactionsResponse: No accounts array found in response');
        $this->transactions = [];
    }
}
