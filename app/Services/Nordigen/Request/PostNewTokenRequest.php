<?php
/*
 * PostNewTokenRequest.php
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
/*
 * PostNewTokenRequest.php
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

use App\Services\Nordigen\Response\TokenSetResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Client;

/**
 * Class PostNewTokenRequest
 */
class PostNewTokenRequest extends Request
{
    private string $identifier;
    private string $key;

    public function __construct(string $identifier, string $key)
    {
        $this->identifier = $identifier;
        $this->key        = $key;
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
        $url    = sprintf('%s/%s', config('nordigen.url'), 'api/v2/token/new/');
        $client = new Client;

        $res  = $client->post($url,
                              [
                                  'json'    => [
                                      'secret_id'  => $this->identifier,
                                      'secret_key' => $this->key,
                                  ],
                                  'headers' => [
                                      'accept'       => 'application/json',
                                      'content-type' => 'application/json',
                                      'user-agent'   => sprintf('Firefly III Universal Data Importer / %s / %s', config('importer.version'), config('auth.line_a')),
                                  ],
                              ]
        );
        $body = (string) $res->getBody();
        $json = json_decode($body, true, JSON_THROW_ON_ERROR);
        return new TokenSetResponse($json);
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
    }
}
