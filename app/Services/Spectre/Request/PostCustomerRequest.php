<?php
/**
 * PostCustomerRequest.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III Spectre importer
 * (https://github.com/firefly-iii/spectre-importer).
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
use App\Services\Spectre\Response\PostCustomerResponse;

/**
 * Class PostCustomerRequest
 */
class PostCustomerRequest extends Request
{
    /** @var string */
    public string $identifier;

    /**
     * PostCustomerRequest constructor.
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
        $this->setUrl('customers');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        // Implement get() method.
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
        if (null === $this->identifier) {
            throw new ImporterErrorException('No identifier for PostCustomerRequest');
        }
        $data = [
            'data' => [
                'identifier' => $this->identifier,
            ],
        ];

        $response = $this->sendSignedSpectrePost($data);

        return new PostCustomerResponse($response['data']);
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // Implement put() method.
    }
}
