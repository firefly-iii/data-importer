<?php

/*
 * GetTransactionsRequest.php
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

namespace App\Services\LunchFlow\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\LunchFlow\Response\GetTransactionsResponse;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;

/**
 * Class GetTransactionsRequest
 */
class GetTransactionsRequest extends Request
{
    private string $identifier = '';
    private int    $account;

    public function __construct(string $apiToken, int $account)
    {
        $this->setApiKey($apiToken);
        $this->account = $account;
        $this->setUrl(sprintf('accounts/%d/transactions', $account));
    }

    /**
     * @throws AgreementExpiredException
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws RateLimitException
     */
    public function get(): Response
    {
        if (false === config('importer.fake_data')) {
            $response = $this->authenticatedGet();
        }
        if (true === config('importer.fake_data')) {
            $response = [
                'transactions' =>
                    [
                            [
                                'id'          => 'c98ce8f4b11342ff0877b33d4a9d395d',
                                'accountId'   => $this->account,
                                'amount'      => -95.35,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-18',
                                'merchant'    => 'Freshto Ideal',
                                'description' => '',
                            ],
                            [
                                'id'          => '5d823e7fa31f227822c3d3394841eaa5',
                                'accountId'   => $this->account,
                                'amount'      => 15,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-17',
                                'merchant'    => 'Jennifer Houston',
                                'description' => '',
                            ],
                            [
                                'id'          => '6ed21381575facccef4385cb4ffbc75e',
                                'accountId'   => $this->account,
                                'amount'      => -95.35,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-17',
                                'merchant'    => 'Freshto Ideal',
                                'description' => '',
                            ],
                            [
                                'id'          => '81ce27ffc76d97d9823906fa23d44677',
                                'accountId'   => $this->account,
                                'amount'      => -95.35,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-17',
                                'merchant'    => 'Freshto Ideal',
                                'description' => '',
                            ],
                            [
                                'id'          => '49a2a3ff8a6916c2c18ab37edae57150',
                                'accountId'   => $this->account,
                                'amount'      => -950,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-16',
                                'merchant'    => 'Liam Brown',
                                'description' => '',
                            ],
                            [
                                'id'          => '60a45baf9e4ccecb173ef7610de7ab86',
                                'accountId'   => $this->account,
                                'amount'      => 15,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-16',
                                'merchant'    => 'Jennifer Houston',
                                'description' => '',
                            ],
                            [
                                'id'          => 'ce53144ec9559a5027e553961deca0ae',
                                'accountId'   => $this->account,
                                'amount'      => 15,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-16',
                                'merchant'    => 'Jennifer Houston',
                                'description' => '',
                            ],
                            [
                                'id'          => '1e34b8105df8c62a7ae6abc0c98ee03e',
                                'accountId'   => $this->account,
                                'amount'      => -95.35,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-16',
                                'merchant'    => 'Freshto Ideal',
                                'description' => '',
                            ],
                            [
                                'id'          => '2eccf8b96a211f7b96f79a30e8f1a4ba',
                                'accountId'   => $this->account,
                                'amount'      => -95.35,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-16',
                                'merchant'    => 'Freshto Ideal',
                                'description' => '',
                            ],
                            [
                                'id'          => '01a8b6aba01a46de3e7af7f306895a26',
                                'accountId'   => $this->account,
                                'amount'      => -35.56,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-15',
                                'merchant'    => 'Liam Brown',
                                'description' => '',
                            ],
                            [
                                'id'          => 'd49b1c3425c565f341f478247e1e5173',
                                'accountId'   => $this->account,
                                'amount'      => -950,
                                'currency'    => 'EUR',
                                'date'        => '2025-10-15',
                                'merchant'    => 'Liam Brown',
                                'description' => '',
                            ],
                    ],
                'total'        => 2927,
            ];
        }
        $transactions = $response['transactions'] ?? [];
        if (!array_key_exists('transactions', $response)) {
            Log::error('No transactions found in response');
        }
        $total = count($transactions);
        Log::debug(sprintf('Downloaded %d transactions from bank account #%d.', $total, $this->account));
        $response = new GetTransactionsResponse($transactions);
        $response->processData();

        return $response;
    }

    public function post(): Response
    {
        //  Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
