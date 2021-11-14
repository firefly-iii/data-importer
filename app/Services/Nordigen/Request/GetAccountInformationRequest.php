<?php
/*
 * GetAccountInformationRequest.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Nordigen importer
 * (https://github.com/firefly-iii/nordigen-importer).
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

namespace App\Services\Nordigen\Request;

use App\Exceptions\ImporterErrorException;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetAccountInformationRequest
 */
class GetAccountInformationRequest extends Request
{
    private string $identifier;

    /**
     * @param string $url
     * @param string $token
     * @param string $identifier
     */
    public function __construct(string $url, string $token, string $identifier)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setIdentifier($identifier);
        $this->setUrl(sprintf('api/v2/accounts/%s/details/', $identifier));
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function get(): Response
    {

        $array = $this->authenticatedGet();
        return new ArrayResponse($array);
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
        // TODO: Implement post() method.
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // TODO: Implement put() method.
    }
}
