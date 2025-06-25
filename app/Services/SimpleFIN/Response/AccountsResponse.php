<?php

/*
 * AccountsResponse.php
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

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AccountsResponse
 */
class AccountsResponse extends SimpleFINResponse
{
    private array $accounts = [];

    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);
        $this->parseAccounts();
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getAccountCount(): int
    {
        return count($this->accounts);
    }

    public function hasAccounts(): bool
    {
        return count($this->accounts) > 0;
    }

    private function parseAccounts(): void
    {
        $data           = $this->getData();

        if (0 === count($data)) {
            Log::warning('SimpleFIN AccountsResponse: No data to parse');

            return;
        }

        // SimpleFIN API returns accounts in the 'accounts' array
        if (isset($data['accounts']) && is_array($data['accounts'])) {
            $this->accounts = $data['accounts'];
            Log::debug(sprintf('SimpleFIN AccountsResponse: Parsed %d accounts', count($this->accounts)));

            return;
        }
        Log::warning('SimpleFIN AccountsResponse: No accounts array found in response');
        $this->accounts = [];
    }
}
