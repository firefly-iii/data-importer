<?php
/*
 * ListBanksRequest.php
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

namespace App\Services\Nordigen\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Response\ErrorResponse;
use App\Services\Nordigen\Response\ListBanksResponse;
use App\Services\Shared\Response\Response;

/**
 * Class ListBanksRequest
 */
class ListBanksRequest extends Request
{
    /**
     * ListCustomersRequest constructor.
     *
     * @param string $url
     * @param string $token
     */
    public function __construct(string $url, string $token)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setUrl('api/v2/institutions/');
    }

    /**
     * @inheritDoc
     * @throws AgreementExpiredException
     */
    public function get(): Response
    {
        try {
            $response = $this->authenticatedGet();
        } catch (ImporterErrorException $e) {
            $error = [
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ];

            return new ErrorResponse($error);
        } catch (ImporterHttpException $e) {
            return new ErrorResponse($e->json ?? []);
        }

        return new ListBanksResponse($response);
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
        // Implement post() method.
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // Implement put() method.
    }
}
