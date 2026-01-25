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

namespace App\Services\EnableBanking;

use App\Services\EnableBanking\Authentication\SecretManager;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthenticationValidator
 */
class AuthenticationValidator implements AuthenticationValidatorInterface
{
    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        if (!SecretManager::hasAppIdAvailable() || !SecretManager::hasPrivateKeyAvailable()) {
            return AuthenticationStatus::NODATA;
        }

        // For Enable Banking, having the credentials is enough
        // The JWT is generated on-the-fly for each request
        if (JWTManager::hasValidCredentials()) {
            return AuthenticationStatus::AUTHENTICATED;
        }

        return AuthenticationStatus::ERROR;
    }

    public function getData(): array
    {
        return [
            'app_id' => SecretManager::getAppId(),
            'private_key' => SecretManager::getPrivateKey(),
        ];
    }

    public function setData(array $data): void
    {
        SecretManager::saveAppId($data['app_id'] ?? '');
        SecretManager::savePrivateKey($data['private_key'] ?? '');
    }
}
