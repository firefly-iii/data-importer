<?php

/*
 * DownloadController.php
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

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Support\Http\RestoresConfiguration;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use JsonException;

/**
 * Class DownloadController
 */
class DownloadController extends Controller
{
    use RestoresConfiguration;

    /**
     * @throws JsonException
     */
    public function download(): Application|Response|ResponseFactory
    {
        // do something
        $configuration = $this->restoreConfiguration();
        $array         = $configuration->toArray();
        $result        = json_encode($array, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $response      = response($result);
        $name          = sprintf('import_config_%s.json', Carbon::now()->format('Y-m-d'));
        $response->header('Content-disposition', sprintf('attachment; filename=%s', $name))
            ->header('Content-Type', 'application/json')
            ->header('Content-Description', 'File Transfer')
            ->header('Connection', 'Keep-Alive')
            ->header('Expires', '0')
            ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->header('Pragma', 'public')
            ->header('Content-Length', (string) strlen($result))
        ;

        return $response;
    }
}
