<?php

/*
 * GetASPSPsRequest.php
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

namespace App\Services\EnableBanking\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\EnableBanking\Response\ASPSPsResponse;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;
use Safe\Exceptions\FilesystemException;

use function Safe\file_get_contents;
use function Safe\file_put_contents;

/**
 * Class GetASPSPsRequest
 * Gets list of available ASPSPs (banks) for a country
 */
final class GetASPSPsRequest extends Request
{
    private string $fakeDataPath = '';

    public function __construct(string $url, string $country)
    {
        $this->setBase($url);
        $this->setUrl(sprintf('aspsps?country=%s', $country));
        $this->fakeDataPath = storage_path(sprintf('fake-data/eb-aspsps-%s.json', $country));
    }

    /**
     * @throws ImporterHttpException
     */
    public function get(): Response
    {
        // perhaps grab fake data instead?
        $grabFake   = (bool) config('importer.fake_data');
        $fakeExists = file_exists($this->fakeDataPath);
        $json       = [];
        if ($grabFake && $fakeExists) {
            Log::debug('Will collect fake data instead of real data.');
            $content = null;

            try {
                $content = file_get_contents($this->fakeDataPath);
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not read fake data: %s', $e->getMessage()));
            }
            if (null !== $content) {
                $json = json_decode($content, true);
            }
        }
        if (!$grabFake || !$fakeExists) {
            $json = $this->authenticatedGet();
        }
        // store fake data in new thing:
        if ($grabFake && !$fakeExists && true === (bool) config('importer.store_fake_data')) {
            Log::debug('Will store this run as fake data to use the next time.');

            try {
                file_put_contents($this->fakeDataPath, json_encode($json));
            } catch (FilesystemException $e) {
                Log::error(sprintf('Could not store fake data: %s', $e->getMessage()));
            }
        }

        return ASPSPsResponse::fromArray($json);
    }

    public function post(): Response
    {
        throw new ImporterHttpException('GetASPSPsRequest does not support POST');
    }
}
