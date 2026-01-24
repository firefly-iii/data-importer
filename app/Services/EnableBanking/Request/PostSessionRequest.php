<?php

/*
 * PostSessionRequest.php
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

namespace App\Services\EnableBanking\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\EnableBanking\Response\SessionResponse;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;

/**
 * Class PostSessionRequest
 * Creates a session after successful authorization
 */
class PostSessionRequest extends Request
{
    private string $code;

    public function __construct(string $url, string $code)
    {
        $this->setBase($url);
        $this->setUrl('sessions');
        $this->code = $code;
    }

    public function get(): Response
    {
        throw new ImporterHttpException('PostSessionRequest does not support GET');
    }

    /**
     * @throws ImporterHttpException
     */
    public function post(): Response
    {
        $data = [
            'code' => $this->code,
        ];

        $json = $this->authenticatedPost($data);

        Log::debug('Enable Banking POST /sessions response:', $json);

        return SessionResponse::fromArray($json);
    }
}
