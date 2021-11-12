<?php
declare(strict_types=1);
/**
 * AutoImport.php
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

namespace App\Console\Commands;

use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\StartImport;
use App\Console\VerifyJSON;
use App\Exceptions\ImportException;
use Illuminate\Console\Command;
use Log;

/**
 * Class AutoImport
 */
class AutoImport extends Command
{
    use HaveAccess, VerifyJSON, StartImport, AutoImports;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Will automatically import from the given directory and use the JSON and CSV files found.';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:auto-import {directory : The directory from which to import automatically.}';
    /** @var string */
    private $directory = './';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $access = $this->haveAccess();
        if (false === $access) {
            $this->error('Could not connect to your local Firefly III instance.');

            return 1;
        }

        $argument        = (string) ($this->argument('directory') ?? './');
        $this->directory = realpath($argument);
        $this->line(sprintf('Going to automatically import everything found in %s (%s)', $this->directory, $argument));

        $files = $this->getFiles();
        if (0 === count($files)) {
            $this->info(sprintf('There are no files in directory %s', $this->directory));
            $this->info('To learn more about this process, read the docs:');
            $this->info('https://docs.firefly-iii.org/csv/install/docker/');

            return 1;
        }
        $this->line(sprintf('Found %d CSV + JSON file sets in %s', count($files), $this->directory));
        try {
            $this->importFiles($files);
        } catch (ImportException $e) {
            Log::error($e->getMessage());
            $this->error(sprintf('Import exception (see the logs): %s', $e->getMessage()));
        }

        return 0;
    }

}
