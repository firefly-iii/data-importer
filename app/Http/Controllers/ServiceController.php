<?php
/*
 * ServiceController.php
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

namespace App\Http\Controllers;

use App\Http\Middleware\ServiceControllerMiddleware;
use App\Services\Enums\AuthenticationStatus;
use App\Services\Nordigen\AuthenticationValidator as NordigenValidator;
use App\Services\Spectre\AuthenticationValidator as SpectreValidator;
use App\Services\SimpleFIN\AuthenticationValidator as SimpleFINValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $this->middleware(ServiceControllerMiddleware::class);
    }

    public function validateNordigen(): JsonResponse
    {
        $validator = new NordigenValidator();
        $result    = $validator->validate();

        if ($result->equals(AuthenticationStatus::error())) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if ($result->equals(AuthenticationStatus::nodata())) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }

    public function validateSimpleFIN(): JsonResponse
    {
        $validator = new SimpleFINValidator();
        $result    = $validator->validate();

        if ($result->equals(AuthenticationStatus::error())) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if ($result->equals(AuthenticationStatus::nodata())) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }

    public function validateSpectre(Request $request): JsonResponse
    {
        $validator = new SpectreValidator();
        $result    = $validator->validate();

        if ($result->equals(AuthenticationStatus::error())) {
            // send user error:
            return response()->json(['result' => 'NOK']);
        }
        if ($result->equals(AuthenticationStatus::nodata())) {
            // send user error:
            return response()->json(['result' => 'NODATA']);
        }

        return response()->json(['result' => 'OK']);
    }
}
