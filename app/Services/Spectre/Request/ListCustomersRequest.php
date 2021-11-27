<?php
/*
 * ListCustomersRequest.php
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

use App\Exceptions\ImporterHttpException;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\ListCustomersResponse;
use App\Services\Shared\Response\Response;

/**
 * Class ListCustomersRequest
 * TODO is not yet paginated.
 */
class ListCustomersRequest extends Request
{
    /**
     * ListCustomersRequest constructor.
     *
     * @param string $url
     * @param string $appId
     * @param string $secret
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->type = 'all';
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setParameters([]);
        $this->setUrl('customers');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        try {
            $response = $this->authenticatedGet();
        } catch (ImporterHttpException $e) {
            // JSON thing.
            return new ErrorResponse($e->json ?? []);
        }

        return new ListCustomersResponse($response['data']);
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
