<?php

/*
 * ServiceController.php
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

namespace App\Http\Controllers;

use App\Services\Enums\AuthenticationStatus;
use App\Services\LunchFlow\AuthenticationValidator as LunchFlowValidator;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\SimpleFIN\AuthenticationValidator as SimpleFINValidator;
use App\Services\Spectre\AuthenticationValidator as SpectreValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class ServiceController
 */
class ServiceController extends Controller
{
    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Importing data...');
    }

    public function validateProvider(string $provider): JsonResponse
    {
        return match ($provider) {
            'nordigen', 'gocardless' => $this->validateNordigen(),
            'simplefin'              => $this->validateSimpleFIN(),
            'spectre'                => $this->validateSpectre(request()),
            'lunchflow'              => $this->validateLunchFlow(),
            'file'                   => response()->json(['result' => 'OK']),
            default                  => response()->json(['result' => 'NOK', 'message' => 'Unknown provider']),
        };
    }

    public function validateNordigen(): JsonResponse
    {
        $validator = new NordigenValidator();
        $result    = $validator->validate();

        if (AuthenticationStatus::ERROR === $result) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if (AuthenticationStatus::NODATA === $result) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }

    public function validateSimpleFIN(): JsonResponse
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $validator = new SimpleFINValidator();
        $result    = $validator->validate();

        if (AuthenticationStatus::ERROR === $result) {
            // send user error:
            Log::error('Error: Could not validate app key.');

            return response()->json(['result' => 'NOK']);
        }
        if (AuthenticationStatus::NODATA === $result) {
            // send user error:
            Log::error('No data: Could not validate app key.');

            return response()->json(['result' => 'NODATA']);
        }
        Log::info(sprintf('[%s] All OK in validateSimpleFIN.', config('importer.version')));

        return response()->json(['result' => 'OK']);
    }

    public function validateSpectre(Request $request): JsonResponse
    {
        $validator = new SpectreValidator();
        $result    = $validator->validate();

        if (AuthenticationStatus::ERROR === $result) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if (AuthenticationStatus::NODATA === $result) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }

    public function validateLunchFlow(): JsonResponse
    {
        $validator = new LunchFlowValidator();
        $result    = $validator->validate();

        if (AuthenticationStatus::ERROR === $result) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if (AuthenticationStatus::NODATA === $result) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }
}
