<?php

/*
 * PutRefreshConnectionRequest.php
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

namespace App\Services\Spectre\Request;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\PutRefreshConnectionResponse;

/**
 * Class PutRefreshConnectionRequest
 */
class PutRefreshConnectionRequest extends Request
{
    public string $connection;

    /**
     * ListCustomersRequest constructor.
     *
     * @param string $url
     * @param string $appId
     * @param string $secret
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('connections/%s/refresh');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function put(): Response
    {
        $this->setUrl(sprintf($this->getUrl(), $this->connection));

        $response = $this->sendUnsignedSpectrePut([]);

        // could be error response:
        if (isset($response['error']) && !isset($response['data'])) {
            return new ErrorResponse($response);
        }

        return new PutRefreshConnectionResponse($response['data']);
    }

    /**
     * @param string $connection
     */
    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }
}
