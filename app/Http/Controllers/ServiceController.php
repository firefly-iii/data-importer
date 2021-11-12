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

use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\TokenManager;
use App\Services\Spectre\Request\ListCustomersRequest;
use App\Services\Spectre\Response\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

/**
 * Class ServiceController
 */
class ServiceController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function validateSpectre(Request $request): JsonResponse
    {
        $error = $this->verifySpectre();

        if (null !== $error) {
            // send user error:
            return response()->json(['result' => 'NOK', 'message' => $error]);
        }

        return response()->json(['result' => 'OK']);
    }

    /**
     * @return JsonResponse
     */
    public function validateNordigen(): JsonResponse
    {
        $error = $this->verifyNordigen();
        if (null !== $error) {
            // send user error:
            return response()->json(['result' => 'NOK', 'message' => $error]);
        }
        return response()->json(['result' => 'OK']);
    }
}
