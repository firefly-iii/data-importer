<?php

/*
 * GetAccountsResponse.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Akahu\Response;

use App\Exceptions\ImporterErrorException;
use App\Services\Akahu\Model\Account\Account;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;

final class GetAccountsResponse extends Response
{
    private array $accounts;

    public function __construct(array $json)
    {
        if (array_key_exists('success', $json) && $json['success']) {
            Log::debug('Akahu GetAccountsRequest returned successfully');

            if (array_key_exists('items', $json)) {
                $this->accounts = array_map(Account::fromJson(...), $json['items']);

                return;
            }
        }

        $msg = 'Akahu api returned badly structured json, expected response to contain';
        $msg .= ' a "success" attribute and an "items" attribute. See logs for more details.';
        Log::error($msg.' json: "'.json_encode($json).'"');

        throw new ImporterErrorException($msg);
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }
}
