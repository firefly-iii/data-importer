<?php

/*
 * DownloadController.php
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
use App\Repository\ImportJob\ImportJobRepository;
use App\Support\Http\RestoresConfiguration;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use JsonException;
use stdClass;

/**
 * Class DownloadController
 */
class DownloadController extends Controller
{
    use RestoresConfiguration;

    private ImportJobRepository $repository;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ImportJobRepository();
    }

    /**
     * @throws JsonException
     */
    public function download(string $identifier): Application|Response|ResponseFactory
    {
        $importJob     = $this->repository->find($identifier);
        $configuration = $importJob->getConfiguration();
        $array         = $configuration->toArray();

        // make sure that "mapping" is an empty object when downloading.
        if (is_array($array['mapping']) && 0 === count($array['mapping'])) {
            $array['mapping'] = new stdClass();
        }
        // same for "accounts"
        if (is_array($array['accounts']) && 0 === count($array['accounts'])) {
            $array['accounts'] = new stdClass();
        }
        // same for "nordigen requisitions"
        if (is_array($array['nordigen_requisitions']) && 0 === count($array['nordigen_requisitions'])) {
            $array['nordigen_requisitions'] = new stdClass();
        }


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
            ->header('Content-Length', (string)strlen($result))
        ;

        return $response;
    }
}
