<?php
declare(strict_types=1);
/**
 * DownloadController.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Http\Controllers\Import;


use App\Http\Controllers\Controller;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

/**
 * Class DownloadController
 */
class DownloadController extends Controller
{
    /**
     * @return ResponseFactory|Response
     */
    public function download()
    {
        // do something
        $configuration = Configuration::fromArray(session()->get(Constants::CONFIGURATION));

        // append the config file with values from the disk:
        $diskArray  = json_decode(StorageService::getContent(session()->get(Constants::UPLOAD_CONFIG_FILE)), true, JSON_THROW_ON_ERROR);
        $diskConfig = Configuration::fromArray($diskArray);

        $configuration->setRoles($diskConfig->getRoles());
        $configuration->setMapping($diskConfig->getMapping());
        $configuration->setDoMapping($diskConfig->getDoMapping());
        $array = $configuration->toArray();

        $result = json_encode($array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT, 512);

        $response = response($result);
        $name     = sprintf('import_config_%s.json', date('Y-m-d'));
        $response->header('Content-disposition', 'attachment; filename=' . $name)
                 ->header('Content-Type', 'application/json')
                 ->header('Content-Description', 'File Transfer')
                 ->header('Connection', 'Keep-Alive')
                 ->header('Expires', '0')
                 ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                 ->header('Pragma', 'public')
                 ->header('Content-Length', strlen($result));

        return $response;
    }

}
