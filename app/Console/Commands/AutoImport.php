<?php
/*
 * AutoImport.php
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

namespace App\Console\Commands;

use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\VerifyJSON;
use Illuminate\Console\Command;

/**
 * Class AutoImport
 */
final class AutoImport extends Command
{
    use AutoImports;
    use HaveAccess;
    use VerifyJSON;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will automatically import from the given directory and use the JSON and importable files found.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = 'importer:auto-import {directory : The directory from which to import automatically.}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $access    = $this->haveAccess();
        if (false === $access) {
            $this->error(sprintf('[a] No access, or no connection is possible to your local Firefly III instance at %s.', config('importer.url')));

            return 64;
        }

        $argument  = (string) ($this->argument('directory') ?? './');

        /** @phpstan-ignore-line */
        $directory = realpath($argument);
        if (false === $directory) {
            $this->error(sprintf('Path "%s" is not a valid location.', $argument));

            return 65;
        }
        if (!$this->isAllowedPath($directory)) {
            $this->error(sprintf('Path "%s" is not in the list of allowed paths (IMPORT_DIR_ALLOWLIST).', $directory));

            return 66;
        }
        $this->line(sprintf('Going to automatically import everything found in %s (%s)', $directory, $argument));

        $files     = $this->getFiles($directory);
        if (0 === count($files)) {
            $this->info(sprintf('There are no files in directory %s', $directory));
            $this->info('To learn more about this process, read the docs:');
            $this->info('https://docs.firefly-iii.org/');

            return 67;
        }
        $this->line(sprintf('Found %d (importable +) JSON file sets in %s', count($files), $directory));


        $result    = $this->importFiles($directory, $files);
        $unique    = array_unique($result);
        if (1 === count($unique)) {
            return (int) reset($result);
        }
        if (count($unique) > 0) {
            $this->warn('Multiple return codes found. Some imports may have failed');
            foreach ($result as $file => $code) {
                $this->warn(sprintf('File %s returned code #%d', $file, $code));
            }

            return 1;
        }

        return 0;
    }
}
