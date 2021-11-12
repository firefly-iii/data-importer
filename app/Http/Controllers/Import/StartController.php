<?php
declare(strict_types=1);
/**
 * StartController.php
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
use App\Http\Middleware\UploadedFiles;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Log;
use Storage;

/**
 * Class StartController
 */
class StartController extends Controller
{
    /**
     * StartController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Import');
        $this->middleware(UploadedFiles::class);
    }

    /**
     * @return Factory|View
     */
    public function index()
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Import routine';
        $subTitle  = 'Start page and instructions';

        // get existing configs.
        $disk = Storage::disk('configurations');
        Log::debug(
            sprintf(
                'Going to check directory for config files: %s',
                config('filesystems.disks.configurations.root'),
            )
        );
        $list = $disk->files();

        Log::debug('List of files:', $list);

        return view('import.index', compact('mainTitle', 'subTitle', 'list'));
    }

}
