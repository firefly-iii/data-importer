<?php
/*
 * AutoUploadController.php
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
use App\Http\Request\AutoUploadRequest;

class AutoUploadController extends Controller
{
    use AutoImports;
    use HaveAccess;
    use VerifyJSON;

    public function error($string, $verbosity = null): void
    {
        app('log')->error($string);
        $this->line($string);
    }

    /**
     * @throws ImporterErrorException
     */
    public function index(AutoUploadRequest $request)
    {
        if (false === config('importer.can_post_files')) {
            throw new ImporterErrorException('Disabled, not allowed to import.');
        }

        $secret         = (string)($request->get('secret') ?? '');
        $systemSecret   = (string)config('importer.auto_import_secret');
        if ('' === $secret || '' === $systemSecret || $secret !== config('importer.auto_import_secret') || strlen($systemSecret) < 16) {
            throw new ImporterErrorException('Bad secret, not allowed to import.');
        }

        $access         = $this->haveAccess();
        if (false === $access) {
            throw new ImporterErrorException(sprintf('Could not connect to your local Firefly III instance at %s.', config('importer.url')));
        }

        $json           = $request->file('json');
        $importable     = $request->file('importable');
        $importablePath = (string) $importable?->getPathname();

        try {
            $this->importUpload((string)$json?->getPathname(), $importablePath);
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            $this->line(sprintf('Import exception (see the logs): %s', $e->getMessage()));
        }

        return ' ';
    }

    /**
     * @param null $verbosity
     */
    public function info($string, $verbosity = null): void
    {
        $this->line($string);
    }

    public function line(string $string): void
    {
        echo sprintf("%s: %s\n", date('Y-m-d H:i:s'), $string);
    }

    /**
     * @param null $verbosity
     */
    public function warn($string, $verbosity = null): void
    {
        $this->line($string);
    }
}
