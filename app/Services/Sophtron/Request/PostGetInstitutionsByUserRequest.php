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

namespace App\Services\Sophtron\Request;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Exceptions\RateLimitException;
use App\Services\Shared\Response\Response;
use App\Services\Sophtron\Response\PostGetInstitutionsByUserResponse;

/**
 * Class GetTransactionsRequest
 */
class PostGetInstitutionsByUserRequest extends Request
{
    public function __construct(string $userId, string $accessKey)
    {
        $this->userId    = $userId;
        $this->accessKey = $accessKey;
        $this->url       = 'api/UserInstitution/GetUserInstitutionsByUser';
        $this->method    = 'POST';
        $this->calculateAuthString();
    }

    /**
     * @throws AgreementExpiredException
     * @throws ImporterErrorException
     * @throws ImporterHttpException
     * @throws RateLimitException
     */
    public function get(): Response
    {
        // no implementado.
    }

    public function post(): Response
    {
        $data = $this->authenticatedPost(
            [
                'userID' => $this->userId,
            ]
        );

        return new PostGetInstitutionsByUserResponse($data);
    }

    public function put(): Response
    {
        // Implement put() method.
    }
}
