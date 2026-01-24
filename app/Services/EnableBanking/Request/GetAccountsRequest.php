<?php

/*
 * GetAccountsRequest.php
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
use App\Services\EnableBanking\Response\AccountsResponse;
use App\Services\Shared\Response\Response;

/**
 * Class GetAccountsRequest
 * Gets accounts for a session
 */
class GetAccountsRequest extends Request
{
    private string $sessionId;

    public function __construct(string $url, string $sessionId)
    {
        $this->setBase($url);
        $this->sessionId = $sessionId;
        // GET /sessions/{session_id} returns session data including accounts
        $this->setUrl(sprintf('sessions/%s', $sessionId));
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): Response
    {
        $json = $this->authenticatedGet();

        return AccountsResponse::fromArray($json, $this->sessionId);
    }

    public function post(): Response
    {
        throw new ImporterHttpException('GetAccountsRequest does not support POST');
    }
}
