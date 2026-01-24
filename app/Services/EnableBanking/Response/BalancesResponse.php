<?php

/*
 * BalancesResponse.php
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

namespace App\Services\EnableBanking\Response;

use App\Services\Shared\Response\Response;

/**
 * Class BalancesResponse
 */
class BalancesResponse extends Response
{
    private array $balances = [];
    private string $accountUid = '';

    public function __construct(array $data = [])
    {
        $this->balances = $data['balances'] ?? $data;
    }

    public static function fromArray(array $array, string $accountUid = ''): self
    {
        $response = new self($array);
        $response->accountUid = $accountUid;

        return $response;
    }

    public function getBalances(): array
    {
        return $this->balances;
    }

    public function getAccountUid(): string
    {
        return $this->accountUid;
    }
}
