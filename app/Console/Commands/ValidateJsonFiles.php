<?php

/*
 * ValidateJsonFiles.php
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

namespace App\Console\Commands;

use App\Console\VerifyJSON;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

class ValidateJsonFiles extends Command
{
    use VerifyJSON;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = 'import:validate-json-directory {directory : The directory with JSON files to validate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recursively validate all JSON files in a directory. Stops after 100 files.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = (string)$this->argument('directory');
        if (!is_dir($directory) || !is_readable($directory)) {
            $this->error(sprintf('Cannot read directory %s.', $directory));

            return CommandAlias::FAILURE;
        }

        // check each file in the directory and see if it needs action.
        // collect recursively:
        $it        = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        $Regex     = new RegexIterator($it, '/^.+\.json$/i', RecursiveRegexIterator::GET_MATCH);
        $fullPaths = [];
        foreach ($Regex as $item) {
            $path        = $item[0];
            $fullPaths[] = $path;
        }
        foreach ($fullPaths as $file) {
            $result = $this->verifyJSON($file);
            if (false === $result) {
                $this->error(sprintf('File "%s" is not valid JSON.', $file));

                return CommandAlias::FAILURE;
            }
            $this->info(sprintf('File "%s" is valid JSON.', $file));
        }

        return CommandAlias::SUCCESS;
    }
}
