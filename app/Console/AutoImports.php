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

use App\Enums\ExitCode;
use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Services\Camt\Conversion\RoutineManager as CamtRoutineManager;
use App\Services\CSV\Conversion\RoutineManager as CSVRoutineManager;
use App\Services\Nordigen\Conversion\RoutineManager as NordigenRoutineManager;
use App\Services\SimpleFIN\Conversion\RoutineManager as SimpleFINRoutineManager;
use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Model\Balance;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Conversion\RoutineStatusManager;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\Shared\Import\Status\SubmissionStatusManager;
use App\Services\Spectre\Conversion\RoutineManager as SpectreRoutineManager;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account as LocalAccount;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Trait AutoImports
 */
trait AutoImports
{
    protected array  $conversionErrors    = [];
    protected array  $conversionMessages  = [];
    protected array  $conversionWarnings  = [];
    protected array $conversionRateLimits = []; // only conversion can have rate limits.
    protected string $identifier;
    protected array  $importErrors        = [];
    protected array  $importMessages      = [];
    protected array  $importWarnings      = [];
    protected array  $importerAccounts    = [];

    private function getFiles(string $directory): array
    {
        $ignore          = ['.', '..'];

        if ('' === $directory) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $directory));

            return [];
        }
        $array           = scandir($directory);
        if (!is_array($array)) {
            $this->error(sprintf('Directory "%s" is empty or invalid.', $directory));

            return [];
        }
        $files           = array_diff($array, $ignore);
        $importableFiles = [];
        $jsonFiles       = [];
        foreach ($files as $file) {
            // import importable file with JSON companion
            if (in_array($this->getExtension($file), ['csv', 'xml'], true)) {
                $importableFiles[] = $file;
            }

            // import JSON config files.
            if ('json' === $this->getExtension($file)) {
                $jsonFiles[] = $file;
            }
        }
        $return          = [];
        foreach ($importableFiles as $importableFile) {
            $jsonFile = $this->getJsonConfiguration($directory, $importableFile);
            if (null !== $jsonFile) {
                $return[$jsonFile] = sprintf('%s/%s', $directory, $importableFile);
            }
        }
        foreach ($jsonFiles as $jsonFile) {
            $fullJson = sprintf('%s/%s', $directory, $jsonFile);
            if (!array_key_exists($fullJson, $return)) {
                $return[$fullJson] = $fullJson;
            }
        }

        return $return;
    }

    private function getExtension(string $file): string
    {
        $parts = explode('.', $file);
        if (1 === count($parts)) {
            return '';
        }

        return strtolower($parts[count($parts) - 1]);
    }

    private function getExtensionLength(string $file): int
    {
        $parts = explode('.', $file);
        if (1 === count($parts)) {
            return 0;
        }

        return strlen($parts[count($parts) - 1]) + 1;
    }

    private function getJsonConfiguration(string $directory, string $file): ?string
    {
        $extensionLength = $this->getExtensionLength($file);
        $short           = substr($file, 0, -$extensionLength);
        $jsonFile        = sprintf('%s.json', $short);
        $fullJson        = sprintf('%s/%s', $directory, $jsonFile);

        if (file_exists($fullJson)) {
            return $fullJson;
        }
        if (Storage::disk('configurations')->exists($jsonFile)) {
            return Storage::disk('configurations')->path($jsonFile);
        }
        $fallbackConfig  = $this->getFallbackConfig($directory);
        if (null !== $fallbackConfig) {
            $this->line('Found fallback configuration file, which will be used for this file.');

            return $fallbackConfig;
        }
        $this->warn(sprintf('Cannot find JSON file "%s" nor fallback file expected to go with file "%s". This file will be ignored.', $jsonFile, $file));

        return null;
    }

    private function getFallbackConfig(string $directory): ?string
    {
        if (false === config('importer.fallback_in_dir')) {
            return null;
        }
        $configJsonFile = sprintf('%s/%s', $directory, config('importer.fallback_configuration'));
        if (file_exists($configJsonFile) && is_readable($configJsonFile)) {
            return $configJsonFile;
        }

        return null;
    }

    private function importFiles(string $directory, array $files): array
    {
        $exitCodes = [];

        foreach ($files as $jsonFile => $importableFile) {
            try {
                $exitCodes[$importableFile] = $this->importFile($jsonFile, $importableFile);
            } catch (ImporterErrorException $e) {
                Log::error(sprintf('Could not complete import from file "%s".', $importableFile));
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                $exitCodes[$importableFile] = 1;
            }
            // report has already been sent. Reset errors and continue.
            $this->conversionErrors     = [];
            $this->conversionMessages   = [];
            $this->conversionWarnings   = [];
            $this->conversionRateLimits = [];
            $this->importErrors         = [];
            $this->importMessages       = [];
            $this->importWarnings       = [];
        }
        Log::debug(sprintf('Collection of exit codes: %s', implode(', ', array_values($exitCodes))));

        return $exitCodes;
    }

    /**
     * @throws ImporterErrorException
     */
    private function importFile(string $jsonFile, string $importableFile): int
    {
        Log::debug(sprintf('ImportFile: importable "%s"', $importableFile));
        Log::debug(sprintf('ImportFile: JSON       "%s"', $jsonFile));

        // do JSON check
        $jsonResult    = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $importableFile, $jsonFile);
            $this->error($message);
            Log::error(sprintf('[%s] Exit code is %s.', config('importer.version'), ExitCode::CANNOT_PARSE_CONFIG->name));

            return ExitCode::CANNOT_PARSE_CONFIG->value;
        }
        $configuration = Configuration::fromArray(json_decode((string) file_get_contents($jsonFile), true));

        // sanity check. If the importableFile is a .json file, and it parses as valid json, don't import it:
        if ('file' === $configuration->getFlow() && str_ends_with(strtolower($importableFile), '.json') && $this->verifyJSON($importableFile)) {
            Log::warning('Almost tried to import a JSON file as a file lol. Skip it.');

            // don't report this.
            Log::debug(sprintf('[%s] Exit code is %s.',config('importer.version'), ExitCode::SUCCESS->name));

            return ExitCode::SUCCESS->value;
        }

        $configuration->updateDateRange();
        $this->line(sprintf('Going to convert from file %s using configuration %s and flow "%s".', $importableFile, $jsonFile, $configuration->getFlow()));

        // this is it!
        $this->startConversion($configuration, $importableFile);
        $this->reportConversion();

        // crash here if the conversion failed.
        if (0 !== count($this->conversionErrors)) {
            $this->error(sprintf('[a] Too many errors in the data conversion (%d), exit.', count($this->conversionErrors)));
            Log::debug(sprintf('[%s] Exit code is %s.',config('importer.version'), ExitCode::TOO_MANY_ERRORS_PROCESSING->name));
            $exitCode = ExitCode::TOO_MANY_ERRORS_PROCESSING->value;

            // could still be that there were simply no transactions (from GoCardless). This can result
            // in another exit code.
            if ($this->isNothingDownloaded()) {
                Log::debug(sprintf('[%s] Exit code changed to %s.', config('importer.version'), ExitCode::NOTHING_WAS_IMPORTED->name));
                $exitCode = ExitCode::NOTHING_WAS_IMPORTED->value;
            }

            // could also be that the end user license agreement is expired.
            if ($this->isExpiredAgreement()) {
                Log::debug(sprintf('[%s] Exit code changed to %s.', config('importer.version'), ExitCode::AGREEMENT_EXPIRED->name));
                $exitCode = ExitCode::AGREEMENT_EXPIRED->value;
            }

            // report about it anyway:
            event(
                new ImportedTransactions(
                    array_merge($this->conversionMessages, $this->importMessages),
                    array_merge($this->conversionWarnings, $this->importWarnings),
                    array_merge($this->conversionErrors, $this->importErrors),
                    $this->conversionRateLimits
                )
            );

            return $exitCode;
        }

        $this->line(sprintf('Done converting from file %s using configuration %s.', $importableFile, $jsonFile));
        $this->startImport($configuration);
        $this->reportImport();
        $this->reportBalanceDifferences($configuration);

        $this->line('Done!');

        // merge things:
        $messages      = array_merge($this->importMessages, $this->conversionMessages);
        $warnings      = array_merge($this->importWarnings, $this->conversionWarnings);
        $errors        = array_merge($this->importErrors, $this->conversionErrors);
        event(new ImportedTransactions($messages, $warnings, $errors, $this->conversionRateLimits));

        if (count($this->importErrors) > 0 || count($this->conversionRateLimits) > 0) {
            Log::error(sprintf('Exit code is %s.', ExitCode::GENERAL_ERROR->name));

            return ExitCode::GENERAL_ERROR->value;
        }
        if (0 === count($messages) && 0 === count($warnings) && 0 === count($errors)) {
            Log::error(sprintf('Exit code is %s.', ExitCode::NOTHING_WAS_IMPORTED->name));

            return ExitCode::NOTHING_WAS_IMPORTED->value;
        }

        Log::error(sprintf('Exit code is %s.', ExitCode::SUCCESS->name));

        return ExitCode::SUCCESS->value;
    }

    /**
     * @throws ImporterErrorException
     */
    private function startConversion(Configuration $configuration, string $importableFile): void
    {
        $this->conversionMessages   = [];
        $this->conversionWarnings   = [];
        $this->conversionErrors     = [];
        $this->conversionRateLimits = [];
        $flow                       = $configuration->getFlow();

        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        if ('' === $importableFile && 'file' === $flow) {
            $this->warn('Importable file path is empty. That means there is no importable file to import.');

            exit(1);
        }

        $manager                    = null;
        if ('file' === $flow) {
            $contentType = $configuration->getContentType();
            if ('unknown' === $contentType) {
                Log::debug('Content type is "unknown" in startConversion(), detect it.');
                $detector    = new FileContentSherlock();
                $contentType = $detector->detectContentType($importableFile);
            }
            if ('unknown' === $contentType || 'csv' === $contentType) {
                Log::debug(sprintf('Content type is "%s" in startConversion(), use the CSV routine.', $contentType));
                $manager          = new CSVRoutineManager(null);
                $this->identifier = $manager->getIdentifier();
                $manager->setContent((string) file_get_contents($importableFile));
            }
            if ('camt' === $contentType) {
                Log::debug('Content type is "camt" in startConversion(), use the CAMT routine.');
                $manager          = new CamtRoutineManager(null);
                $this->identifier = $manager->getIdentifier();
                $manager->setContent((string) file_get_contents($importableFile));
            }
        }
        if ('nordigen' === $flow) {
            $manager          = new NordigenRoutineManager(null);
            $this->identifier = $manager->getIdentifier();
        }
        if ('spectre' === $flow) {
            $manager          = new SpectreRoutineManager(null);
            $this->identifier = $manager->getIdentifier();
        }
        if ('simplefin' === $flow) {
            $manager          = new SimpleFINRoutineManager(null);
            $this->identifier = $manager->getIdentifier();
        }
        if (null === $manager) {
            $this->error(sprintf('There is no support for flow "%s"', $flow));

            exit(1);
        }

        RoutineStatusManager::startOrFindConversion($this->identifier);
        RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_RUNNING, $this->identifier);

        // then push stuff into the routine:
        $manager->setConfiguration($configuration);
        $transactions               = [];

        try {
            $transactions = $manager->start();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $this->identifier);
            $this->conversionMessages   = $manager->getAllMessages();
            $this->conversionWarnings   = $manager->getAllWarnings();
            $this->conversionErrors     = $manager->getAllErrors();
            $this->conversionRateLimits = $manager->getAllRateLimits();
        }
        if (0 === count($transactions)) {
            Log::error('[a] Zero transactions!');
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_DONE, $this->identifier);
            $this->conversionMessages   = $manager->getAllMessages();
            $this->conversionWarnings   = $manager->getAllWarnings();
            $this->conversionErrors     = $manager->getAllErrors();
            $this->conversionRateLimits = $manager->getAllRateLimits();
        }

        // save transactions in 'jobs' directory under the same key as the conversion thing.
        $disk                       = \Storage::disk('jobs');

        try {
            $disk->put(sprintf('%s.json', $this->identifier), json_encode($transactions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            Log::error(sprintf('JSON exception: %s', $e->getMessage()));
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_ERRORED, $this->identifier);
            $this->conversionMessages   = $manager->getAllMessages();
            $this->conversionWarnings   = $manager->getAllWarnings();
            $this->conversionErrors     = $manager->getAllErrors();
            $this->conversionRateLimits = $manager->getAllRateLimits();
            $transactions               = [];
        }

        if (count($transactions) > 0) {
            // set done:
            RoutineStatusManager::setConversionStatus(ConversionStatus::CONVERSION_DONE, $this->identifier);

            $this->conversionMessages   = $manager->getAllMessages();
            $this->conversionWarnings   = $manager->getAllWarnings();
            $this->conversionErrors     = $manager->getAllErrors();
            $this->conversionRateLimits = $manager->getAllRateLimits();
        }
        $this->importerAccounts     = $manager->getServiceAccounts();
    }

    private function reportConversion(): void
    {
        $list = [
            [$this->conversionMessages, 'info'],
            [$this->conversionWarnings, 'warn'],
            [$this->conversionErrors, 'error'],
            [$this->conversionRateLimits, 'warn'],
        ];
        foreach ($list as $set) {
            /** @var string $func */
            $func = $set[1];

            /** @var array $all */
            $all  = $set[0];

            /**
             * @var int   $index
             * @var array $messages
             */
            foreach ($all as $index => $messages) {
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $this->{$func}(sprintf('Conversion index (%s) %d: %s', $func, $index, $message)); // @phpstan-ignore-line
                    }
                }
            }
        }
    }

    private function startImport(Configuration $configuration): void
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $routine              = new RoutineManager($this->identifier);
        SubmissionStatusManager::startOrFindSubmission($this->identifier);
        $disk                 = \Storage::disk('jobs');
        $fileName             = sprintf('%s.json', $this->identifier);

        // get files from disk:
        if (!$disk->has($fileName)) {
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
            $message              = sprintf('[a100]: File "%s" not found, cannot continue.', $fileName);
            $this->error($message);
            SubmissionStatusManager::addError($this->identifier, 0, $message);
            $this->importMessages = $routine->getAllMessages();
            $this->importWarnings = $routine->getAllWarnings();
            $this->importErrors   = $routine->getAllErrors();

            return;
        }

        try {
            $json         = $disk->get($fileName);
            $transactions = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            Log::debug(sprintf('Found %d transactions on the drive.', count($transactions)));
        } catch (FileNotFoundException|JsonException $e) {
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_ERRORED, $this->identifier);
            $message              = sprintf('[a101]: File "%s" could not be decoded, cannot continue..', $fileName);
            $this->error($message);
            SubmissionStatusManager::addError($this->identifier, 0, $message);
            $this->importMessages = $routine->getAllMessages();
            $this->importWarnings = $routine->getAllWarnings();
            $this->importErrors   = $routine->getAllErrors();

            return;
        }
        if (0 === count($transactions)) {
            SubmissionStatusManager::setSubmissionStatus(SubmissionStatus::SUBMISSION_DONE, $this->identifier);
            Log::error('No transactions in array, there is nothing to import.');
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
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
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

    private function reportImport(): void
    {
        $list = [
            'info'  => $this->importMessages,
            'warn'  => $this->importWarnings,
            'error' => $this->importErrors,
        ];

        $this->info(sprintf('There are %d message(s)', count($this->importMessages)));
        $this->info(sprintf('There are %d warning(s)', count($this->importWarnings)));
        $this->info(sprintf('There are %d error(s)', count($this->importErrors)));

        foreach ($list as $func => $set) {
            /**
             * @var int   $index
             * @var array $messages
             */
            foreach ($set as $index => $messages) {
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $this->{$func}(sprintf('Import index %d: %s', $index, $message)); // @phpstan-ignore-line
                    }
                }
            }
        }
    }

    private function reportBalanceDifferences(Configuration $configuration): void
    {
        if ('nordigen' !== $configuration->getFlow()) {
            return;
        }
        $count         = count($this->importerAccounts);
        $localAccounts = $configuration->getAccounts();
        $url           = SecretManager::getBaseUrl();
        $token         = SecretManager::getAccessToken();
        Log::debug(sprintf('The importer has collected %d account(s) to report the balance difference on.', $count));

        /** @var Account $account */
        foreach ($this->importerAccounts as $account) {
            // check if account exists:
            if (!array_key_exists($account->getIdentifier(), $localAccounts)) {
                Log::debug(sprintf('GoCardless account "%s" (IBAN "%s") is not being imported, so skipped.', $account->getIdentifier(), $account->getIban()));

                continue;
            }
            // local account ID exists, we can check the balance over at Firefly III.
            $accountId      = $localAccounts[$account->getIdentifier()];
            $accountRequest = new GetAccountRequest($url, $token);
            $accountRequest->setVerify(config('importer.connection.verify'));
            $accountRequest->setTimeOut(config('importer.connection.timeout'));
            $accountRequest->setId($accountId);

            try {
                /** @var GetAccountResponse $result */
                $result = $accountRequest->get();
            } catch (ApiHttpException $e) {
                Log::error('Could not get Firefly III account for balance check. Will ignore this issue.');
                Log::debug($e->getMessage());

                continue;
            }

            $localAccount   = $result->getAccount();

            $this->reportBalanceDifference($account, $localAccount);
        }
    }

    private function reportBalanceDifference(Account $account, LocalAccount $localAccount): void
    {
        Log::debug(sprintf('Report balance difference between GoCardless account "%s" and Firefly III account #%d.', $account->getIdentifier(), $localAccount->id));
        Log::debug(sprintf('GoCardless account has %d balance entry (entries)', count($account->getBalances())));

        /** @var Balance $balance */
        foreach ($account->getBalances() as $index => $balance) {
            Log::debug(sprintf('Now comparing balance entry "%s" (#%d of %d)', $balance->type, $index + 1, count($account->getBalances())));
            $this->reportSingleDifference($account, $localAccount, $balance);
        }
    }

    private function reportSingleDifference(Account $account, LocalAccount $localAccount, Balance $balance): void
    {
        // compare currencies, and warn if necessary.
        if ($balance->currency !== $localAccount->currencyCode) {
            Log::warning(sprintf('GoCardless account "%s" has currency %s, Firefly III account #%d uses %s.', $account->getIdentifier(), $localAccount->id, $balance->currency, $localAccount->currencyCode));
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: Currency mismatch', $balance->type, $localAccount->id));
        }

        // compare dates, warn
        $date      = Carbon::parse($balance->date);
        $localDate = Carbon::parse($localAccount->currentBalanceDate);
        if (!$date->isSameDay($localDate)) {
            Log::warning(sprintf('GoCardless balance is from day %s, Firefly III account from %s.', $date->format('Y-m-d'), $date->format('Y-m-d')));
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: Date mismatch', $balance->type, $localAccount->id));
        }

        // compare balance, warn (also a message)
        Log::debug(sprintf('Comparing %s and %s', $balance->amount, $localAccount->currentBalance));
        if (0 !== bccomp($balance->amount, (string) $localAccount->currentBalance)) {
            Log::warning(sprintf('GoCardless balance is %s, Firefly III balance is %s.', $balance->amount, $localAccount->currentBalance));
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: GoCardless reports %s %s, Firefly III reports %s %d', $balance->type, $localAccount->id, $balance->currency, $balance->amount, $localAccount->currencyCode, $localAccount->currentBalance));
        }
        if (0 === bccomp($balance->amount, (string) $localAccount->currentBalance)) {
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: Balance OK', $balance->type, $localAccount->id));
        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function importUpload(string $jsonFile, string $importableFile): void
    {
        // do JSON check
        $jsonResult    = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $importableFile, $jsonFile);
            $this->error($message);

            return;
        }
        $configuration = Configuration::fromArray(json_decode((string) file_get_contents($jsonFile), true));
        $configuration->updateDateRange();

        $this->line(sprintf('Going to convert from file "%s" using configuration "%s" and flow "%s".', $importableFile, $jsonFile, $configuration->getFlow()));

        // this is it!
        $this->startConversion($configuration, $importableFile);
        $this->reportConversion();

        // crash here if the conversion failed.
        if (0 !== count($this->conversionErrors)) {
            $this->error(sprintf('[b] Too many errors in the data conversion (%d), exit.', count($this->conversionErrors)));

            throw new ImporterErrorException('Too many errors in the data conversion.');
        }

        $this->line(sprintf('Done converting from file %s using configuration %s.', $importableFile, $jsonFile));
        $this->startImport($configuration);
        $this->reportImport();

        $this->line('Done!');
        event(
            new ImportedTransactions(
                array_merge($this->conversionMessages, $this->importMessages),
                array_merge($this->conversionWarnings, $this->importWarnings),
                array_merge($this->conversionErrors, $this->importErrors),
                $this->conversionRateLimits
            )
        );
    }

    protected function isNothingDownloaded(): bool
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

    protected function isExpiredAgreement(): bool
    {
        /** @var array $errors */
        foreach ($this->conversionErrors as $errors) {
            /** @var string $error */
            foreach ($errors as $error) {
                if (str_contains($error, 'EUA') && str_contains($error, 'expired')) {
                    return true;
                }
            }
        }

        return false;
    }
}
