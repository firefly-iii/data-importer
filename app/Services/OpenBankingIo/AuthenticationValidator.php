<?php

/*
 * AuthenticationValidator.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo;

use App\Services\Enums\AuthenticationStatus;
use App\Services\OpenBankingIo\Authentication\SecretManager;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthenticationValidator
 */
final class AuthenticationValidator implements AuthenticationValidatorInterface
{
    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $credentials = SecretManager::getCredentials();

        if ('' === $credentials) {
            return AuthenticationStatus::NODATA;
        }

        $parsed      = SecretManager::parse($credentials);
        if ('' === $parsed['apiKey'] || '' === $parsed['privateKey']) {
            return AuthenticationStatus::NODATA;
        }

        return AuthenticationStatus::AUTHENTICATED;
    }

    public function getData(): array
    {
        return ['credentials' => SecretManager::getCredentials()];
    }

    public function setData(array $data): void
    {
        SecretManager::saveCredentials((string) ($data['credentials'] ?? ''));
    }
}
