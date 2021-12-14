<?php
/*
 * AutoImportController.php
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


use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\VerifyJSON;
use App\Exceptions\ImporterErrorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;

/**
 *
 */
class AutoImportController extends Controller
{
    use HaveAccess, AutoImports, VerifyJSON;

    private string $directory;

    /**
     *
     */
    public function index(Request $request): Response
    {
        $access = $this->haveAccess();
        if (false === $access) {
            throw new ImporterErrorException('Could not connect to your local Firefly III instance.');
        }
        $argument        = (string) ($request->get('directory') ?? './');
        $directory = realpath($argument);

        if(!$this->isAllowedPath($directory)) {
            throw new ImporterErrorException('Not allowed to import from this path.');
        }

        // take code from auto importer.
        app('log')->info(sprintf('Going to automatically import everything found in %s (%s)', $directory, $argument));

        $files = $this->getFiles($directory);
        if (0 === count($files)) {
            return response('');
        }
        app('log')->info(sprintf('Found %d (CSV +) JSON file sets in %s', count($files), $directory));
        try {
            $this->importFiles($directory, $files);
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            throw new ImporterErrorException(sprintf('Import exception (see the logs): %s', $e->getMessage()));
        }
        return response('');
    }

    public function line(string $string)
    {
        echo sprintf("%s: %s\n", date('Y-m-d H:i:s'), $string);
    }

    /**
     * @inheritDoc
     */
    public function error($string, $verbosity = null)
    {
        $this->line($string);
    }

    /**
     * @param      $string
     * @param null $verbosity
     */
    public function warn($string, $verbosity = null)
    {
        $this->line($string);
    }

    /**
     * @param      $string
     * @param null $verbosity
     */
    public function info($string, $verbosity = null)
    {
        $this->line($string);
    }
}
