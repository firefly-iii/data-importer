<?php

/*
 * AuthenticationValidator.php
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

namespace App\Services\Nordigen;

use App\Exceptions\ImporterHttpException;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Nordigen\Authentication\SecretManager;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use App\Services\Shared\Authentication\IsRunningCli;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthenticationValidator
 */
class AuthenticationValidator implements AuthenticationValidatorInterface
{

    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $identifier = SecretManager::getId();
        $key        = SecretManager::getKey();

        if ('' === $identifier || '' === $key) {
            return AuthenticationStatus::NODATA;
        }

        // is there a valid access and refresh token?
        if (TokenManager::hasValidRefreshToken() && TokenManager::hasValidAccessToken()) {
            return AuthenticationStatus::AUTHENTICATED;
        }

        if (TokenManager::hasExpiredRefreshToken()) {
            // refresh!
            TokenManager::getFreshAccessToken();
        }

        // get complete set!
        try {
            TokenManager::getNewTokenSet($identifier, $key);
        } catch (ImporterHttpException) {
            return AuthenticationStatus::ERROR;
        }

        return AuthenticationStatus::AUTHENTICATED;
    }

    public function getData(): array
    {
        return [
            'identifier' => SecretManager::getId(),
            'key'        => SecretManager::getKey(),
        ];
    }

    public function setData(array $data): void
    {
        SecretManager::saveId($data['identifier']);
        SecretManager::saveKey($data['key']);
    }
}
