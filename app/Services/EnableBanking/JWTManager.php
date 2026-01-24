<?php

/*
 * JWTManager.php
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
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;

/**
 * Class JWTManager
 * Generates RS256 JWT tokens for Enable Banking API authentication
 */
class JWTManager
{
    private const int TOKEN_EXPIRY_SECONDS = 3600; // 1 hour

    /**
     * Generate a JWT token for Enable Banking API authentication
     */
    public static function generateToken(): string
    {
        Log::debug('Generating Enable Banking JWT token');

        $appId = SecretManager::getAppId();
        $privateKey = SecretManager::getPrivateKey();

        $now = time();
        $payload = [
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => $now,
            'exp' => $now + self::TOKEN_EXPIRY_SECONDS,
        ];

        // The 4th parameter is the key ID (kid) which must be included in the JWT header
        // Enable Banking requires kid to be the Application ID
        $token = JWT::encode($payload, $privateKey, 'RS256', $appId);
        Log::debug('Enable Banking JWT token generated successfully');

        return $token;
    }

    /**
     * Check if we have valid credentials to generate a token
     */
    public static function hasValidCredentials(): bool
    {
        $appId = SecretManager::getAppId();
        $privateKey = SecretManager::getPrivateKey();

        return '' !== $appId && '' !== $privateKey;
    }
}
