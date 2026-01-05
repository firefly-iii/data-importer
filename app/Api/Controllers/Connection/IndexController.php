<?php

declare(strict_types=1);
/*
 * IndexController.php
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

namespace App\Api\Controllers\Connection;

use App\Api\Controllers\Controller;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\SystemInformationRequest;
use GrumpyDictator\FFIIIApiSupport\Response\SystemInformationResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IndexController extends Controller
{
    public function validateConnection(): JsonResponse
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $response        = ['result' => 'OK', 'message' => null, 'status_code' => 0];

        // Check if OAuth is configured but no session token exists
        $clientId        = (string)config('importer.client_id');
        $configToken     = (string)config('importer.access_token');

        // Corrected: Use the constant value directly with session helper
        Log::debug(sprintf('Has valid secrets according to API call: %s', var_export(SecretManager::hasValidSecrets(), true)));
        $sessionHasToken = session()->has(Constants::SESSION_ACCESS_TOKEN) && '' !== session()->get(Constants::SESSION_ACCESS_TOKEN);

        if ('' !== $clientId && '' === $configToken && !$sessionHasToken) {
            Log::debug('OAuth configured but no session token - needs authentication');

            return response()->json(['result' => 'NEEDS_OAUTH', 'message' => 'OAuth authentication required', 'status_code' => 0]);
        }

        // get values from secret manager:
        $url             = SecretManager::getBaseUrl();
        $token           = SecretManager::getAccessToken();
        $infoRequest     = new SystemInformationRequest($url, $token);

        $infoRequest->setVerify(config('importer.connection.verify'));
        $infoRequest->setTimeOut(config('importer.connection.timeout'));
        Log::debug(sprintf('Now trying to authenticate with Firefly III at %s', $url));

        try {
            /** @var SystemInformationResponse $result */
            $result = $infoRequest->get();
        } catch (ApiHttpException $e) {
            Log::notice(sprintf('Could NOT authenticate with Firefly III at %s', $url));
            Log::error(sprintf('Could not connect to Firefly III: %s', $e->getMessage()));
            Log::debug(sprintf('Using access token "%s" (limited to 25 chars if present)', substr($token, 0, 25)));
            $statusCode = $e?->response->getStatusCode();

            return response()->json(['result' => 'NOK', 'message' => $e->getMessage(), 'status_code' => $statusCode]);
        }
        // -1 = OK (minimum is smaller)
        // 0 = OK (same version)
        // 1 = NOK (too low a version)

        $minimum         = (string)config('importer.minimum_version');
        $compare         = version_compare($minimum, $result->version);

        if (str_starts_with($result->version, 'develop')) {
            // overrule compare, because the user is running a develop version
            Log::warning(sprintf('[%s] You are connecting to a development version of Firefly III (%s). This may not work as expected.', config('importer.version'), $result->version));
            $compare = -1;
        }
        if (str_starts_with($result->version, 'branch')) {
            // overrule compare, because the user is running a branch version
            Log::warning(sprintf('[%s] You are connecting to a branch version of Firefly III (%s). This may not work as expected.', config('importer.version'), $result->version));
            $compare = -1;
        }

        if (str_starts_with($result->version, 'branch')) {
            // overrule compare, because the user is running a develop version
            Log::warning(sprintf('[%s] You are connecting to a branch version of Firefly III (%s). This may not work as expected.', config('importer.version'), $result->version));
            $compare = -1;
        }

        if (1 === $compare) {
            $errorMessage = sprintf('Your Firefly III version %s is below the minimum required version %s', $result->version, $minimum);
            Log::error(sprintf('Could not link to Firefly III: %s', $errorMessage));
            $response     = ['result' => 'NOK', 'message' => $errorMessage];
        }
        Log::debug('Result is', $response);

        return response()->json($response);
    }
}
