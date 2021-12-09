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
use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Console\Command;

/**
 * Class Import
 */
class Import extends Command
{
    use HaveAccess, VerifyJSON, AutoImports;

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
    {file? : Optionally, the CSV file you want to import}
    ';

    /**
     * Execute the console command.
     *
     * @return int
     * @throws ImporterErrorException
     */
    public function handle(): int
    {
        $access = $this->haveAccess();
        if (false === $access) {
            $this->error('Could not connect to your local Firefly III instance.');

            return 1;
        }

        $this->info(sprintf('Welcome to the Firefly III data importer, v%s', config('importer.version')));
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $file   = (string) $this->argument('file');
        $config = (string) $this->argument('config');

        if (!file_exists($config) || (file_exists($config) && !is_file($config))) {
            $message = sprintf('The importer can\'t import: configuration file "%s" does not exist or could not be read.', $config);
            $this->error($message);
            app('log')->error($message);

            return 1;
        }


        $jsonResult = $this->verifyJSON($config);
        if (false === $jsonResult) {
            $message = 'The importer can\'t import: could not decode the JSON in the config file.';
            $this->error($message);

            return 1;
        }
        $configuration = Configuration::fromArray(json_decode(file_get_contents($config), true));
        if ('csv' === $configuration->getFlow() && (!file_exists($file) || (file_exists($file) && !is_file($file)))) {
            $message = sprintf('The importer can\'t import: CSV file "%s" does not exist or could not be read.', $file);
            $this->error($message);
            app('log')->error($message);

            return 1;
        }

        $this->line('The import routine is about to start.');
        $this->line('This is invisible and may take quite some time.');
        $this->line('Once finished, you will see a list of errors, warnings and messages (if applicable).');
        $this->line('--------');
        $this->line('Running...');

        // first do conversion based on the file:
        $this->startConversion($configuration, $file);
        $this->reportConversion();

        $this->line(sprintf('Done converting from file %s using configuration %s.', $file, $config));
        $this->startImport($configuration);
        $this->reportImport();

        $this->line('Done!');

        event(new ImportedTransactions($this->importMessages, $this->importWarnings, $this->importErrors));
        return 0;

    }
}
