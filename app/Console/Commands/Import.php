<?php
/*
 * Import.php
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
use App\Enums\ExitCode;
use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Console\Command;

/**
 * Class Import
 */
final class Import extends Command
{
    use AutoImports;
    use HaveAccess;
    use VerifyJSON;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import into Firefly III. Requires a configuration file and optionally a configuration file.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:import
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
        $access = $this->haveAccess();
        if (false === $access) {
            $this->error(sprintf('No access granted, or no connection is possible to your local Firefly III instance at %s.', config('importer.url')));
            app('log')->error(sprintf('Exit code is %s.', ExitCode::NO_CONNECTION->name));

            return ExitCode::NO_CONNECTION->value;
        }

        $this->info(sprintf('Welcome to the Firefly III data importer, v%s', config('importer.version')));
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $file   = (string) $this->argument('file');
        $config = (string) $this->argument('config'); // @phpstan-ignore-line

        // validate config path:
        if ('' !== $config) {
            $directory = dirname($config);
            if (!$this->isAllowedPath($directory)) {
                $this->error(sprintf('Path "%s" is not in the list of allowed paths (IMPORT_DIR_ALLOWLIST).', $directory));
                app('log')->error(sprintf('Exit code is %s.', ExitCode::INVALID_PATH->name));

                return ExitCode::INVALID_PATH->value;
            }
        }

        // validate file path
        if ('' !== $file) {
            $directory = dirname($file);
            if (!$this->isAllowedPath($directory)) {
                $this->error(sprintf('Path "%s" is not in the list of allowed paths (IMPORT_DIR_ALLOWLIST).', $directory));
                app('log')->error(sprintf('Exit code is %s.', ExitCode::NOT_ALLOWED_PATH->name));

                return ExitCode::NOT_ALLOWED_PATH->value;
            }
        }

        if (!file_exists($config) || (file_exists($config) && !is_file($config))) {
            $message = sprintf('The importer can\'t import: configuration file "%s" does not exist or could not be read.', $config);
            $this->error($message);
            app('log')->error($message);
            app('log')->error(sprintf('Exit code is %s.', ExitCode::CANNOT_READ_CONFIG->name));

            return ExitCode::CANNOT_READ_CONFIG->value;
        }

        $jsonResult = $this->verifyJSON($config);
        if (false === $jsonResult) {
            $message = 'The importer can\'t import: could not decode the JSON in the config file.';
            $this->error($message);
            app('log')->error(sprintf('Exit code is %s.', ExitCode::CANNOT_PARSE_CONFIG->name));

            return ExitCode::CANNOT_PARSE_CONFIG->value;
        }
        $configuration = Configuration::fromArray(json_decode(file_get_contents($config), true));
        if ('file' === $configuration->getFlow() && (!file_exists($file) || (file_exists($file) && !is_file($file)))) {
            $message = sprintf('The importer can\'t import: importable file "%s" does not exist or could not be read.', $file);
            $this->error($message);
            app('log')->error($message);

            app('log')->error(sprintf('Exit code is %s.', ExitCode::IMPORTABLE_FILE_NOT_FOUND->name));

            return ExitCode::IMPORTABLE_FILE_NOT_FOUND->value;
        }

        $configuration->updateDateRange();

        $this->line('The import routine is about to start.');
        $this->line('This is invisible and may take quite some time.');
        $this->line('Once finished, you will see a list of errors, warnings and messages (if applicable).');
        $this->line('--------');
        $this->line('Running...');

        // first do conversion based on the file:
        $this->startConversion($configuration, $file);
        $this->reportConversion();

        // crash here if the conversion failed.
        $exitCode = ExitCode::SUCCESS->value;
        if (0 !== count($this->conversionErrors)) {
            app('log')->error(sprintf('Exit code is %s.', ExitCode::TOO_MANY_ERRORS_PROCESSING->name));
            $exitCode = ExitCode::TOO_MANY_ERRORS_PROCESSING->value;
            // could still be that there were simply no transactions (from GoCardless). This can result
            // in another exit code.
            if($this->isNothingDownloaded()) {
                app('log')->error(sprintf('Exit code changed to %s.', ExitCode::NOTHING_WAS_IMPORTED->name));
                $exitCode = ExitCode::NOTHING_WAS_IMPORTED->value;
            }

            $this->error('There are many errors in the data conversion. The import will stop here.');
        }
        if (0 === count($this->conversionErrors)) {
            $this->line(sprintf('Done converting from file %s using configuration %s.', $file, $config));
            $this->startImport($configuration);
            $this->reportImport();
            $this->line('Done!');
        }

        $this->reportBalanceDifferences($configuration);

        // merge things:
        $messages = array_merge($this->importMessages, $this->conversionMessages);
        $warnings = array_merge($this->importWarnings, $this->conversionWarnings);
        $errors   = array_merge($this->importErrors, $this->conversionErrors);

        event(new ImportedTransactions($messages, $warnings, $errors, $this->conversionRateLimits));
        if (0 !== count($this->importErrors)) {
            $exitCode = ExitCode::GENERAL_ERROR->value;
            app('log')->error(sprintf('Exit code is %s.', ExitCode::GENERAL_ERROR->name));
        }
        if (0 === count($messages) && 0 === count($warnings) && 0 === count($errors)) {
            $exitCode = ExitCode::NOTHING_WAS_IMPORTED->value;
            app('log')->error(sprintf('Exit code is %s.', ExitCode::NOTHING_WAS_IMPORTED->name));
        }
        if ($exitCode === ExitCode::SUCCESS->value) {
            app('log')->debug(sprintf('Exit code is %s.', ExitCode::SUCCESS->name));
        }

        return $exitCode;
    }

    private function isNothingDownloaded(): bool
    {
        /** @var array $errors */
        foreach ($this->conversionErrors as $errors) {
            /** @var string $error */
            foreach ($errors as $error) {
                if (str_contains($error, '[a111]')) {
                    return true;
                }
            }
        }
        return false;
    }
}
