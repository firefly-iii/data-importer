<?php

/*
 * AuthenticationValidator.php
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

namespace App\Services\LunchFlow;

use App\Exceptions\ImporterHttpException;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Nordigen\Authentication\SecretManager;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Authentication\AuthenticationValidatorInterface;
use App\Services\Shared\Authentication\IsRunningCli;
use Illuminate\Support\Facades\Log;

/**
 * Class AuthenticationValidator
 */
class AuthenticationValidator implements AuthenticationValidatorInterface
{
    use IsRunningCli;

    public function validate(): AuthenticationStatus
    {
        Log::debug(sprintf('Now at %s', __METHOD__));

        $apiKey = config('lunchflow.api_key');

        if ('' === $apiKey) {
            return AuthenticationStatus::NODATA;
        }

        return AuthenticationStatus::AUTHENTICATED;
    }
}
