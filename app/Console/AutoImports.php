<?php
/*
 * AutoImports.php
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

namespace App\Console;

use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Services\Spectre\Conversion\RoutineManager as SpectreRoutineManager;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use Storage;

/**
 * Trait AutoImports
 */
trait AutoImports
{
    protected array  $conversionMessages = [];
    protected array  $conversionWarnings = [];
    protected array  $conversionErrors   = [];
    protected array  $importMessages     = [];
    protected array  $importWarnings     = [];
    protected array  $importErrors       = [];
    protected string $identifier;

    /**
     * @param string $directory
     * @return array
     */
    protected function getFiles(string $directory): array
    {
        $ignore = ['.', '..'];

        if ('' === $directory) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $directory));

            return [];
        }
        $array = scandir($directory);
        if (!is_array($array)) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $directory));

            return [];
        }
        $files  = array_diff($array, $ignore);
        $return = [];
        foreach ($files as $file) {
            // import CSV file with JSON companion
            if ('csv' === $this->getExtension($file) && $this->hasJsonConfiguration($directory, $file)) {
                $return[] = $file;
            }
            // import JSON with no CSV
            if ('json' === $this->getExtension($file) && !$this->hasCsvFile($directory, $file)) {
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
     * @param string $directory
     * @param string $file
     *
     * @return bool
     */
    private function hasJsonConfiguration(string $directory, string $file): bool
    {
        $short    = substr($file, 0, -4);
        $jsonFile = sprintf('%s.json', $short);
        $fullJson = sprintf('%s/%s', $directory, $jsonFile);
        if (!file_exists($fullJson)) {
            $this->warn(sprintf('Can\'t find JSON file "%s" expected to go with CSV file "%s". CSV file will be ignored.', $fullJson, $file));

            return false;
        }

        return true;
    }

    /**
     * @param string $directory
     * @param string $file
     *
     * @return bool
     */
    private function hasCsvFile(string $directory, string $file): bool
    {
        $short    = substr($file, 0, -5);
        $csvFile  = sprintf('%s.csv', $short);
        $fullJson = sprintf('%s/%s', $directory, $csvFile);
        if (!file_exists($fullJson)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $directory
     * @param array  $files
     *
     * @throws ImporterErrorException
     */
    protected function importFiles(string $directory, array $files): void
    {
        /** @var string $file */
        foreach ($files as $file) {
            $this->importFile($directory, $file);
        }
    }

    /**
     * @param string $file
     * @param string $directory
     * @throws ImporterErrorException
     */
    private function importFile(string $directory, string $file): void
    {
        $csvFile  = sprintf('%s/%s', $directory, $file);
        $jsonFile = sprintf('%s/%s.json', $directory, substr($file, 0, -5));

        // do JSON check
        $jsonResult = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $csvFile, $jsonFile);
            $this->error($message);

            return;
        }
        $configuration = Configuration::fromArray(json_decode(file_get_contents($jsonFile), true));
        $configuration->updateDateRange();


        $this->line(sprintf('Going to convert from file %s using configuration %s and flow "%s".', $csvFile, $jsonFile, $configuration->getFlow()));

        // this is it!
        $this->startConversion($configuration, $csvFile);
        $this->reportConversion();

        $this->line(sprintf('Done converting from file %s using configuration %s.', $csvFile, $jsonFile));
        $this->startImport($configuration);
        $this->reportImport();

        $this->line('Done!');
        event(new ImportedTransactions(
                  array_merge($this->conversionMessages, $this->importMessages),
                  array_merge($this->conversionWarnings, $this->importWarnings),
                  array_merge($this->conversionErrors, $this->importErrors)
              )
        );

    }


    /**
     * @param Configuration $configuration
     *
     * @param string|null   $csvFile
     * @throws ImporterErrorException
     */
    private function startConversion(Configuration $configuration, ?string $csvFile): void
    {
        $this->conversionMessages = [];
        $this->conversionWarnings = [];
        $this->conversionErrors   = [];

        app('log')->debug(sprintf('Now in %s', __METHOD__));

        switch ($configuration->getFlow()) {
            default:
                $this->error(sprintf('There is no support for flow "%s"', $configuration->getFlow()));
                exit();
            case 'csv':
                // create importer
                $manager          = new CSVRoutineManager(null);
                $this->identifier = $manager->getIdentifier();
                $manager->setContent(file_get_contents($csvFile));
                break;
            case 'nordigen':
                $manager          = new NordigenRoutineManager(null);
                $this->identifier = $manager->getIdentifier();
                break;
            case 'spectre':
                $manager          = new SpectreRoutineManager(null);
                $this->identifier = $manager->getIdentifier();
                break;
        }

        RoutineStatusManager::startOrFindConversion($this->identifier);
        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_RUNNING, $this->identifier);

        // then push stuff into the routine:
        $manager->setConfiguration($configuration);
        $transactions = [];
        try {
            $transactions = $manager->start();
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $this->identifier);
            $this->conversionMessages = $manager->getAllMessages();
            $this->conversionWarnings = $manager->getAllWarnings();
            $this->conversionErrors   = $manager->getAllErrors();
        }
        if (0 === count($transactions)) {
            app('log')->error('Zero transactions!');
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $this->identifier);
            $this->conversionMessages = $manager->getAllMessages();
            $this->conversionWarnings = $manager->getAllWarnings();
            $this->conversionErrors   = $manager->getAllErrors();

        }
        // save transactions in 'jobs' directory under the same key as the conversion thing.
        $disk = Storage::disk('jobs');
        try {
            $disk->put(sprintf('%s.json', $this->identifier), json_encode($transactions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            app('log')->error(sprintf('JSON exception: %s', $e->getMessage()));
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $this->identifier);
            $this->conversionMessages = $manager->getAllMessages();
            $this->conversionWarnings = $manager->getAllWarnings();
            $this->conversionErrors   = $manager->getAllErrors();
            $transactions             = [];
        }

        if (count($transactions) > 0) {
            // set done:
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_DONE, $this->identifier);

            $this->conversionMessages = $manager->getAllMessages();
            $this->conversionWarnings = $manager->getAllWarnings();
            $this->conversionErrors   = $manager->getAllErrors();
        }
    }

    /**
     *
     */
    private function reportConversion(): void
    {
        $list = [
            'info'  => $this->conversionMessages,
            'warn'  => $this->conversionWarnings,
            'error' => $this->conversionErrors,
        ];
        foreach ($list as $func => $set) {
            /**
             * @var int   $index
             * @var array $messages
             */
            foreach ($set as $index => $messages) {
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $this->$func(sprintf('Conversion index %d: %s', $index, $message));
                    }
                }
            }
        }
    }

    /**
     * @param Configuration $configuration
     */
    private function startImport(Configuration $configuration): void
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $routine = new RoutineManager($this->identifier);
        SubmissionStatusManager::startOrFindSubmission($this->identifier);
        $disk     = Storage::disk('jobs');
        $fileName = sprintf('%s.json', $this->identifier);

        // get files from disk:
        if (!$disk->has($fileName)) {
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
            $message = sprintf('File "%s" not found, cannot continue.', $fileName);
            $this->error($message);
            SubmissionStatusManager::addError($this->identifier, 0, $message);
            $this->importMessages = $routine->getAllMessages();
            $this->importWarnings = $routine->getAllWarnings();
            $this->importErrors   = $routine->getAllErrors();
            return;
        }


        try {
            $json         = $disk->get($fileName);
            $transactions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            app('log')->debug(sprintf('Found %d transactions on the drive.', count($transactions)));
        } catch (FileNotFoundException | JsonException $e) {
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
            $message = sprintf('File "%s" could not be decoded, cannot continue..', $fileName);
            $this->error($message);
            SubmissionStatusManager::addError($this->identifier, 0, $message);
            $this->importMessages = $routine->getAllMessages();
            $this->importWarnings = $routine->getAllWarnings();
            $this->importErrors   = $routine->getAllErrors();
            return;
        }

        $routine->setTransactions($transactions);

        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_RUNNING, $this->identifier);

        // then push stuff into the routine:
        $routine->setConfiguration($configuration);
        try {
            $routine->start();
        } catch (ImporterErrorException $e) {
            app('log')->error($e->getMessage());
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
            SubmissionStatusManager::addError($this->identifier, 0, $e->getMessage());
            $this->importMessages = $routine->getAllMessages();
            $this->importWarnings = $routine->getAllWarnings();
            $this->importErrors   = $routine->getAllErrors();
            return;
        }

        // set done:
        SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_DONE, $this->identifier);
        $this->importMessages = $routine->getAllMessages();
        $this->importWarnings = $routine->getAllWarnings();
        $this->importErrors   = $routine->getAllErrors();
    }

    /**
     *
     */
    private function reportImport(): void
    {
        $list = [
            'info'  => $this->importMessages,
            'warn'  => $this->importWarnings,
            'error' => $this->importErrors,
        ];
        foreach ($list as $func => $set) {
            /**
             * @var int   $index
             * @var array $messages
             */
            foreach ($set as $index => $messages) {
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $this->$func(sprintf('Import index %d: %s', $index, $message));
                    }
                }
            }
        }
    }
}
