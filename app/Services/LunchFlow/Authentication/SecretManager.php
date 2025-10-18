<?php

/*
 * SecretManager.php
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

namespace App\Services\LunchFlow\Authentication;

use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Log;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const string API_KEY = 'lunch_flow_api_key';

    public static function getApiKey(?Configuration $configuration = null): string
    {

        if (!self::hasApiKey()) {
            Log::debug('LunchFlow: No API key in hasApiKey() session, will return config OR Configuration variable.');

            $apiKey = (string)config('lunchflow.api_key');
            if ('' !== $apiKey) {
                return $apiKey;
            }

            return (string)$configuration?->getLunchFlowApiKey();
        }

        return (string)session()->get(self::API_KEY);
    }

    /**
     * Will verify if the user has a Spectre App ID (in a cookie)
     */
    private static function hasApiKey(): bool
    {
        return '' !== (string)session()->get(self::API_KEY);
    }

    public static function saveApiKey(string $apiKey): void
    {
        session()->put(self::API_KEY, $apiKey);
    }
}
