<?php

/*
 * Import.php
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

use App\Console\AutoImports;
use App\Console\HaveAccess;
use App\Console\VerifyJSON;
use App\Enums\ExitCode;
use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Class Import
 */
final class Import extends Command
{
    use AutoImports;
    use HaveAccess;
    use VerifyJSON;

    protected $description = 'Import into Firefly III. Requires a configuration file and optionally a configuration file.';
    protected $signature   = 'importer:import
    {config : The configuration file. }
    {file? : Optionally, the importable file you want to import}
    ';

    /**
     * Execute the console command.
     *
     * @throws ImporterErrorException
     */
    public function handle(): int
    {
        $access        = $this->haveAccess();
        if (false === $access) {
            $this->error(sprintf('No access granted, or no connection is possible to your local Firefly III instance at %s.', config('importer.url')));
            Log::error(sprintf('Exit code is %s.', ExitCode::NO_CONNECTION->name));

            return ExitCode::NO_CONNECTION->value;
        }

        $this->info(sprintf('Welcome to the Firefly III data importer, v%s', config('importer.version')));
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $file          = (string) $this->argument('file');
        $config        = (string) $this->argument('config'); // @phpstan-ignore-line

        // validate config path:
        if ('' !== $config) {
            $directory = dirname($config);
            if (!$this->isAllowedPath($directory)) {
                $this->error(sprintf('Path "%s" is not in the list of allowed paths (IMPORT_DIR_ALLOWLIST).', $directory));
                Log::error(sprintf('Exit code is %s.', ExitCode::INVALID_PATH->name));

                return ExitCode::INVALID_PATH->value;
            }
        }

        // validate file path
        if ('' !== $file) {
            $directory = dirname($file);
            if (!$this->isAllowedPath($directory)) {
                $this->error(sprintf('Path "%s" is not in the list of allowed paths (IMPORT_DIR_ALLOWLIST).', $directory));
                Log::error(sprintf('Exit code is %s.', ExitCode::NOT_ALLOWED_PATH->name));

                return ExitCode::NOT_ALLOWED_PATH->value;
            }
        }

        if (!file_exists($config) || !is_file($config)) {
            $message = sprintf('The importer can\'t import: configuration file "%s" does not exist or could not be read.', $config);
            $this->error($message);
            Log::error($message);
            Log::error(sprintf('Exit code is %s.', ExitCode::CANNOT_READ_CONFIG->name));

            return ExitCode::CANNOT_READ_CONFIG->value;
        }

        $jsonResult    = $this->verifyJSON($config);
        if (false === $jsonResult) {
            $message = 'The importer can\'t import: could not decode the JSON in the config file.';
            $this->error($message);
            Log::error(sprintf('Exit code is %s.', ExitCode::CANNOT_PARSE_CONFIG->name));

            return ExitCode::CANNOT_PARSE_CONFIG->value;
        }

        // 2025-12-20. Create new import job and use that instead.
        $exitCode = $this->importFileAsImportJob($config, $file);

        // merge things:
        $messages      = array_merge($this->importMessages, $this->conversionMessages);
        $warnings      = array_merge($this->importWarnings, $this->conversionWarnings);
        $errors        = array_merge($this->importErrors, $this->conversionErrors);

        event(new ImportedTransactions(basename($config), $messages, $warnings, $errors, $this->conversionRateLimits));
        if (0 !== count($this->importErrors)) {
            $exitCode = ExitCode::GENERAL_ERROR->value;
            Log::error(sprintf('Exit code is %s.', ExitCode::GENERAL_ERROR->name));
        }
        if (0 === count($messages) && 0 === count($warnings) && 0 === count($errors)) {
            $exitCode = ExitCode::NOTHING_WAS_IMPORTED->value;
            Log::error(sprintf('Exit code is %s.', ExitCode::NOTHING_WAS_IMPORTED->name));
        }
        if ($exitCode === ExitCode::SUCCESS->value) {
            Log::debug(sprintf('Exit code is %s.', ExitCode::SUCCESS->name));
        }

        return $exitCode;
    }
}
