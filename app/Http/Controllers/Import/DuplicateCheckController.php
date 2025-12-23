<?php

/*
 * DuplicateCheckController.php
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

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfigurationControllerMiddleware;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Session\Constants;
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
    private ImportJobRepository $repository;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(ConfigurationControllerMiddleware::class);
        $this->repository = new ImportJobRepository();
    }

    /**
     * Check if an account name and type combination already exists
     */
    public function checkDuplicate(Request $request, string $identifier): JsonResponse
    {
        $importJob           = $this->repository->find($identifier);
        $applicationAccounts = $importJob->getApplicationAccounts();
        $name                = trim((string)$request->input('name', ''));
        $type                = trim((string)$request->input('type', ''));

        if ('' === $name || '' === $type) {
            Log::debug('DUPLICATE_CHECK: Empty name or type, returning no duplicate');

            return response()->json(
                [
                    'isDuplicate' => false,
                    'message'     => null,
                ]
            );
        }
        // Validate account type
        $validTypes          = ['asset', 'liability'];
        if (!in_array($type, $validTypes, true)) {
            Log::warning('DUPLICATE_CHECK: Invalid account type provided', [
                'type'        => $type,
                'valid_types' => $validTypes,
            ]);

            return response()->json(
                [
                    'isDuplicate' => false,
                    'message'     => null,
                ]
            );
        }
        $arrayToCheck        = [
            'asset'     => Constants::ASSET_ACCOUNTS,
            'liability' => Constants::LIABILITIES,
        ];
        $array               = $applicationAccounts[$arrayToCheck[$type]] ?? [];
        $isDuplicate         = false;
        foreach ($array as $account) {
            if (strtolower($name) === strtolower($account['name'])) {
                $isDuplicate = true;
            }
        }
        $message             = null;
        if ($isDuplicate) {
            $message = sprintf('%s <em>%s</em> already exists!', ucfirst($type), $name);
        }

        Log::debug('DUPLICATE_CHECK: Validation result', [
            'name'        => $name,
            'type'        => $type,
            'isDuplicate' => $isDuplicate,
            'message'     => $message,
        ]);

        return response()->json(
            [
                'isDuplicate' => $isDuplicate,
                'message'     => $message,
            ]
        );

    }
}
