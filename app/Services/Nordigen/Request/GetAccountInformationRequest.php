<?php
/*
 * GetAccountInformationRequest.php
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
use App\Exceptions\RateLimitException;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetAccountInformationRequest
 */
class GetAccountInformationRequest extends Request
{
    private string $identifier;

    public function __construct(string $url, string $token, string $identifier)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setIdentifier($identifier);
        $this->setUrl(sprintf('api/v2/accounts/%s/details/', $identifier));
    }

    /**
     * @throws ImporterErrorException
     * @throws AgreementExpiredException
     * @throws RateLimitException
     */
    public function get(): Response
    {
        $array = $this->authenticatedGet();

        return new ArrayResponse($array);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function post(): Response
    {
        // Implement post() method.
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
