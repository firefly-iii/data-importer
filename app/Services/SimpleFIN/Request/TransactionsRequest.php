<?php

/*
 * TransactionsRequest.php
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

namespace App\Services\SimpleFIN\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\Shared\Response\ResponseInterface as SharedResponseInterface;
use App\Services\SimpleFIN\Response\TransactionsResponse;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Safe\Exceptions\FilesystemException;
use function Safe\file_get_contents;
use function Safe\file_put_contents;

/**
 * Class TransactionsRequest
 */
final class TransactionsRequest extends SimpleFINRequest
{
    private string $fakeDataPath = '';

    public function __construct()
    {
        $this->fakeDataPath = storage_path('fake-data/simplefin-transactions.json');
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): SharedResponseInterface
    {
        if (!config('importer.collect_fake_data') && config('importer.fake_data')) {
            Log::debug('Will collect fake data instead of real data.');
            try {
                $content = file_get_contents($this->fakeDataPath);
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not read fake data: %s', $e->getMessage()));
                throw new ImporterHttpException(sprintf('Could not read fake data: %s', $e->getMessage()));
            }
            return new TransactionsResponse(new Response(200, [], $content));
        }


        Log::debug(sprintf('Now at %s', __METHOD__));

        $response = $this->authenticatedGet('');

        if (config('importer.collect_fake_data') && !config('importer.fake_data')) {
            Log::debug('Will store this run as fake data to use the next time.');
            // store this run.
            try {
                file_put_contents($this->fakeDataPath, (string)$response->getBody());
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not store fake data: %s', $e->getMessage()));
            }
        }

        return new TransactionsResponse($response);
    }
}
