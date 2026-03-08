<?php

/*
 * PostRefreshConnectionRequest.php
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

namespace App\Services\Spectre\Request;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\PostRefreshConnectionResponse;
use SensitiveParameter;

/**
 * Class PostRefreshConnectionRequest
 */
class PostRefreshConnectionRequest extends Request
{
    public string $connection;

    /**
     * ListCustomersRequest constructor.
     */
    public function __construct(string $url, string $appId, #[SensitiveParameter] string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('connections/%s/refresh');
    }

    public function get(): Response
    {
        throw new ImporterHttpException('Method not implemented');
    }

    /**
     * @throws ImporterErrorException
     */
    public function post(): Response
    {
        $this->setUrl(sprintf($this->getUrl(), $this->connection));

        $body = ['data' => [
            'return_connection_id' => false,
            'automatic_refresh'    => true,
            'show_widget'          => false,
            'attempt'              => ['fetch_scopes' => ['accounts', 'transactions'], 'return_to'    => $this->getUrl()],
        ]];

        try {
            $response = $this->sendUnsignedSpectrePost($body);
        } catch (ImporterHttpException $e) {
            // This probably means that the connection has just been refreshed so let's ignore it and continue with import
            if (str_contains($e->getMessage(), 'ConnectionCannotBeRefreshed')) {
                return new PostRefreshConnectionResponse([]);
            }

            throw $e;
        }

        // could be error response:
        if (array_key_exists('error', $response) && !array_key_exists('data', $response)) {
            return new ErrorResponse($response);
        }

        // response data is not used, no need to include it.
        // return new PostRefreshConnectionResponse($response['data']);
        return new PostRefreshConnectionResponse([]);
    }

    public function put(): Response
    {
        throw new ImporterHttpException('Method not implemented');
    }

    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }
}
