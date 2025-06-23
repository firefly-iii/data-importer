<?php

/*
 * DuplicateCheckController.php
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

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class DuplicateCheckController
 *
 * Provides AJAX endpoint for real-time duplicate account validation
 */
class DuplicateCheckController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(ConfigurationControllerMiddleware::class);
    }

    /**
     * Check if an account name and type combination already exists
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        try {
            $name        = trim($request->input('name', ''));
            $type        = trim($request->input('type', ''));

            Log::debug('DUPLICATE_CHECK: Received request', [
                'name'        => $name,
                'type'        => $type,
                'name_length' => strlen($name),
            ]);

            // Empty name or type means no duplicate possible
            if ('' === $name || '' === $type) {
                Log::debug('DUPLICATE_CHECK: Empty name or type, returning no duplicate');

                return response()->json([
                    'isDuplicate' => false,
                    'message'     => null,
                ]);
            }

            // Validate account type
            $validTypes  = ['asset', 'liability', 'expense', 'revenue'];
            if (!in_array($type, $validTypes, true)) {
                Log::warning('DUPLICATE_CHECK: Invalid account type provided', [
                    'type'        => $type,
                    'valid_types' => $validTypes,
                ]);

                return response()->json([
                    'isDuplicate' => false,
                    'message'     => null,
                ]);
            }

            // Create validator instance and check for duplicates
            $validator   = new ConfigurationContractValidator();
            $isDuplicate = $validator->checkSingleAccountDuplicate($name, $type);

            $message     = null;
            if ($isDuplicate) {
                $message = sprintf('%s <em>%s</em> already exists!', ucfirst($type), $name);
            }

            Log::debug('DUPLICATE_CHECK: Validation result', [
                'name'        => $name,
                'type'        => $type,
                'isDuplicate' => $isDuplicate,
                'message'     => $message,
            ]);

            return response()->json([
                'isDuplicate' => $isDuplicate,
                'message'     => $message,
            ]);

        } catch (\Exception $e) {
            Log::error('DUPLICATE_CHECK: Exception during duplicate check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'name'  => $request->input('name', ''),
                'type'  => $request->input('type', ''),
            ]);

            // Return safe response on error - assume no duplicate to avoid blocking user
            return response()->json([
                'isDuplicate' => false,
                'message'     => null,
                'error'       => 'Unable to check for duplicates at this time',
            ]);
        }
    }
}
