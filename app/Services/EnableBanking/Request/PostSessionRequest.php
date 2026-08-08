<?php

/*
 * PostSessionRequest.php
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
use App\Services\EnableBanking\Response\SessionResponse;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;
use Safe\Exceptions\FilesystemException;

/**
 * Class PostSessionRequest
 * Creates a session after successful authorization
 */
final class PostSessionRequest extends Request
{
    private string $code;
    private string $fakeDataPath = '';

    public function __construct(string $url, string $code)
    {
        $this->setBase($url);
        $this->setUrl('sessions');
        $this->code = $code;
        $this->fakeDataPath = storage_path(sprintf('fake-data/eb-post-session-request-%s.json', $code));
    }

    public function get(): Response
    {
        throw new ImporterHttpException('PostSessionRequest does not support GET');
    }

    /**
     * @throws ImporterHttpException
     */
    public function post(): Response
    {
        // perhaps grab fake data instead?
        $grabFake   = (bool) config('importer.fake_data');
        $fakeExists = file_exists($this->fakeDataPath);
        $json = [];
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
            $data = ['code' => $this->code];
            $json = $this->authenticatedPost($data);
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


        Log::debug('Enable Banking POST /sessions response:', $json);

        return SessionResponse::fromArray($json);
    }
}
