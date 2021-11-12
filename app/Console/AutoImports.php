<?php
declare(strict_types=1);
/*
 * AutoImports.php
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

namespace App\Console;

use App\Exceptions\ImportException;
use App\Mail\ImportFinished;
use Illuminate\Support\Facades\Mail;
use JsonException;
use Log;

/**
 * Trait AutoImports
 */
trait AutoImports
{
    /**
     * @return array
     */
    protected function getFiles(): array
    {
        $ignore = ['.', '..'];

        if (null === $this->directory || '' === $this->directory) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $this->directory));

            return [];
        }
        $array = scandir($this->directory);
        if (!is_array($array)) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $this->directory));

            return [];
        }
        $files  = array_diff($array, $ignore);
        $return = [];
        foreach ($files as $file) {
            if ('csv' === $this->getExtension($file) && $this->hasJsonConfiguration($file)) {
                $return[] = $file;
            }
        }

        return $return;
    }


    /**
     * @param string $file
     *
     * @return string
     */
    private function getExtension(string $file): string
    {
        $parts = explode('.', $file);
        if (1 === count($parts)) {
            return '';
        }

        return strtolower($parts[count($parts) - 1]);
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    private function hasJsonConfiguration(string $file): bool
    {
        $short    = substr($file, 0, -4);
        $jsonFile = sprintf('%s.json', $short);
        $fullJson = sprintf('%s/%s', $this->directory, $jsonFile);
        if (!file_exists($fullJson)) {
            $this->warn(sprintf('Can\'t find JSON file "%s" expected to go with CSV file "%s". CSV file will be ignored.', $fullJson, $file));

            return false;
        }

        return true;
    }

    /**
     * @param array $files
     *
     * @throws ImportException
     */
    protected function importFiles(array $files): void
    {
        /** @var string $file */
        foreach ($files as $file) {
            $this->importFile($file);
        }
    }

    /**
     * @param string $file
     *
     * @throws ImportException
     */
    private function importFile(string $file): void
    {
        $csvFile  = sprintf('%s/%s', $this->directory, $file);
        $jsonFile = sprintf('%s/%s.json', $this->directory, substr($file, 0, -4));

        // do JSON check
        $jsonResult = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $csvFile, $jsonFile);
            $this->error($message);

            return;
        }
        try {
            $configuration = json_decode(file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
            throw new ImportException(sprintf('Bad JSON in configuration file: %s', $e->getMessage()));
        }
        $this->line(sprintf('Going to import from file %s using configuration %s.', $csvFile, $jsonFile));
        // create importer
        $csv    = file_get_contents($csvFile);
        $result = $this->startImport($csv, $configuration);

        if (0 === $result) {
            $this->line('Import complete.');
        }
        if (0 !== $result) {
            $this->warn('The import finished with errors.');
        }

        $this->line(sprintf('Done importing from file %s using configuration %s.', $csvFile, $jsonFile));

        // send mail:
        $log
            = [
            'messages' => $this->messages,
            'warnings' => $this->warnings,
            'errors'   => $this->errors,
        ];

        $send = config('mail.enable_mail_report');
        Log::debug('Log log', $log);
        if (true === $send) {
            Log::debug('SEND MAIL');
            Mail::to(config('mail.destination'))->send(new ImportFinished($log));
        }
    }

    /**
     * @param string $file
     *
     * @throws ImportException
     */
    private function importUpload(string $csvFile, string $jsonFile): void
    {
        // do JSON check
        $jsonResult = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $csvFile, $jsonFile);
            $this->error($message);

            return;
        }
        try {
            $configuration = json_decode(file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error($e->getMessage());
            throw new ImportException(sprintf('Bad JSON in configuration file: %s', $e->getMessage()));
        }
        $this->line(sprintf('Going to import from file %s using configuration %s.', $csvFile, $jsonFile));
        // create importer
        $csv    = file_get_contents($csvFile);
        $result = $this->startImport($csv, $configuration);

        if (0 === $result) {
            $this->line('Import complete.');
        }
        if (0 !== $result) {
            $this->warn('The import finished with errors.');
        }

        $this->line(sprintf('Done importing from file %s using configuration %s.', $csvFile, $jsonFile));

        // send mail:
        $log
            = [
            'messages' => $this->messages,
            'warnings' => $this->warnings,
            'errors'   => $this->errors,
        ];

        $send = config('mail.enable_mail_report');
        Log::debug('Log log', $log);
        if (true === $send) {
            Log::debug('SEND MAIL');
            Mail::to(config('mail.destination'))->send(new ImportFinished($log));
        }
    }
}
