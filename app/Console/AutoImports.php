<?php

/*
 * AutoImports.php
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

namespace App\Console;

use App\Enums\ExitCode;
use App\Events\ImportedTransactions;
use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Repository\ImportJob\ImportJobRepository;
use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Model\Balance;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionRoutineFactory;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Import\Routine\RoutineManager;
use App\Services\Shared\Import\Status\SubmissionStatus;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account as LocalAccount;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Trait AutoImports
 */
trait AutoImports
{
    protected array               $conversionErrors     = [];
    protected array               $conversionMessages   = [];
    protected array               $conversionWarnings   = [];
    protected array               $conversionRateLimits = []; // only conversion can have rate limits.
    protected string              $identifier;
    protected array               $importErrors         = [];
    protected array               $importMessages       = [];
    protected array               $importWarnings       = [];
    protected array               $importerAccounts     = [];
    protected ImportJobRepository $repository;
    private ImportJob             $importJob;

    private function getFiles(string $directory): array
    {
        Log::debug(sprintf('Now in getFiles("%s")', $directory));
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
            $ext = $this->getExtension($file);
            // import importable file with JSON companion
            if (in_array($ext, ['csv', 'xml'], true)) {
                $importableFiles[] = $file;
                Log::debug(sprintf('Added "%s" to the list of importable files.', $file));
            }

            // import JSON config files.
            if ('json' === $ext) {
                $jsonFiles[] = $file;
                Log::debug(sprintf('Added "%s" to the list of JSON files.', $file));
            }
        }
        $return          = [];
        foreach ($importableFiles as $importableFile) {
            Log::debug(sprintf('Find JSON for importable file "%s".', $importableFile));
            $jsonFile = $this->getJsonConfiguration($directory, $importableFile);
            if (null !== $jsonFile) {
                $return[$jsonFile] ??= [];
                $return[$jsonFile][] = sprintf('%s/%s', $directory, $importableFile);
                Log::debug(sprintf('Found JSON: "%s".', $jsonFile));

                continue;
            }
            Log::debug(sprintf('Found NO JSON for importable file "%s", will be ignored.', $importableFile));
        }
        foreach ($jsonFiles as $jsonFile) {
            $fullJson = sprintf('%s/%s', $directory, $jsonFile);
            if (!array_key_exists($fullJson, $return)) {
                $return[$fullJson] ??= [];
                $return[$fullJson][] = $fullJson;
                Log::debug(sprintf('Add JSON file to the list of things to import: %s', $fullJson));
            }
        }
        Log::debug('Set of importable files:', $return);

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

        foreach ($files as $jsonFile => $importableFiles) {
            foreach ($importableFiles as $importableFile) {
                try {
                    $exitCodes[$importableFile] = $this->importFileAsImportJob($jsonFile, $importableFile);
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
        }
        Log::debug(sprintf('Collection of exit codes: %s', implode(', ', array_values($exitCodes))));

        return $exitCodes;
    }

    /**
     * @throws ImporterErrorException
     */
    private function importFileAsImportJob(string $jsonFile, string $importableFile): int
    {
        Log::debug(sprintf('importFileAsImportJob: importable "%s"', $importableFile));
        Log::debug(sprintf('importFileAsImportJob: JSON       "%s"', $jsonFile));

        // FIXME this is a hack. Normally, the data importer would know what import flow to use from the user's selection.
        // FIXME but now we parse the config (which we know is valid), take the flow, and give it to the import job.
        $jsonContent      = file_get_contents($jsonFile);
        $json             = json_decode($jsonContent, true);

        Log::debug(sprintf('JSON says the flow is "%s"', $json['flow']));

        // create new import job:
        $this->repository = new ImportJobRepository();
        $importJob        = $this->repository->create();
        $importJob        = $this->repository->setFlow($importJob, $json['flow']);
        $importJob        = $this->repository->setConfigurationString($importJob, $jsonContent);
        if ('' !== $importableFile) {
            $importJob = $this->repository->setImportableFileString($importJob, file_get_contents($importableFile));
        }
        $importJob        = $this->repository->markAs($importJob, 'contains_content');

        // FIXME: this little routine belongs in a function or a helper.
        // FIXME: it is duplicated
        // at this point, also parse and process the uploaded configuration file string.
        $configuration    = Configuration::make();
        if ('' !== $jsonContent && null === $importJob->getConfiguration()) {
            $configuration = Configuration::fromArray($json);
        }
        if (null !== $importJob->getConfiguration()) {
            $configuration = $importJob->getConfiguration();
        }
        $importJob->setConfiguration($configuration);
        $this->importJob  = $importJob;
        $this->repository->saveToDisk($importJob);
        $messages         = $this->repository->parseImportJob($importJob);

        if ($messages->count() > 0) {
            if ($messages->has('missing_requisitions') && 'true' === (string)$messages->get('missing_requisitions')[0]) {
                $this->error('Your import is missing a necessary GoCardless requisitions.');

                return ExitCode::NO_REQUISITIONS_PRESENT->value;
            }
            foreach ($messages as $message) {
                $this->error(sprintf('Error message: %s', $message));
            }

            return ExitCode::GENERAL_ERROR->value;
        }

        // sanity check. If the importableFile is a .json file, and it parses as valid json, don't import it:
        if ('file' === $configuration->getFlow() && str_ends_with(strtolower($importableFile), '.json') && $this->verifyJSON($importableFile)) {
            Log::warning('Almost tried to import a JSON file as a file lol. Skip it.');

            // don't report this.
            Log::debug(sprintf('[%s] Exit code is %s.', config('importer.version'), ExitCode::GENERAL_ERROR->name));

            return ExitCode::GENERAL_ERROR->value;
        }

        $this->line(sprintf('[a] Going to convert from file "%s" using configuration %s and flow "%s".', $importableFile, $jsonFile, $configuration->getFlow()));
        $this->importJob  = $importJob;
        $this->repository->saveToDisk($importJob);
        // this is it!
        $importJob        = $this->startConversionFromImportJob($importJob);
        $this->reportConversion();
        $this->importJob  = $importJob;
        $this->repository->saveToDisk($importJob);

        // crash here if the conversion failed.
        if (0 !== count($this->conversionErrors)) {
            $this->error(sprintf('[a] Too many errors in the data conversion (%d), exit.', count($this->conversionErrors)));
            Log::debug(sprintf('[%s] Exit code is %s.', config('importer.version'), ExitCode::TOO_MANY_ERRORS_PROCESSING->name));
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
                    basename($jsonFile),
                    array_merge($importJob->conversionStatus->messages, $importJob->submissionStatus->messages),
                    array_merge($importJob->conversionStatus->warnings, $importJob->submissionStatus->warnings),
                    array_merge($importJob->conversionStatus->errors, $importJob->submissionStatus->errors),
                    $importJob->conversionStatus->rateLimits
                )
            );

            return $exitCode;
        }

        $this->line(sprintf('Done converting from file %s using configuration %s.', $importableFile, $jsonFile));
        $this->startImportFromImportJob($importJob);
        $this->reportImport();
        $this->reportBalanceDifferences($importJob);

        $this->line('Done!');

        // merge things:
        $messages         = array_merge($importJob->conversionStatus->messages, $importJob->submissionStatus->messages);
        $warnings         = array_merge($importJob->conversionStatus->warnings, $importJob->submissionStatus->warnings);
        $errors           = array_merge($importJob->conversionStatus->errors, $importJob->submissionStatus->errors);
        event(new ImportedTransactions(basename($jsonFile), $messages, $warnings, $errors, $importJob->conversionStatus->rateLimits));

        if (count($errors) > 0) {
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
    private function startConversionFromImportJob(ImportJob $importJob): ImportJob
    {
        $this->conversionMessages   = [];
        $this->conversionWarnings   = [];
        $this->conversionErrors     = [];
        $this->conversionRateLimits = [];
        $flow                       = $importJob->getFlow();
        $configuration              = $importJob->getConfiguration();
        $this->repository->parseImportJob($importJob);
        $this->repository->saveToDisk($importJob);

        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        $factory                    = new ConversionRoutineFactory($importJob);
        $manager                    = $factory->createManager();
        Log::debug(sprintf('Routine created: %s.', $manager::class));
        Log::debug('About to call start()');
        $importJob->conversionStatus->setStatus(ConversionStatus::CONVERSION_RUNNING);

        // then push stuff into the routine:
        $transactions               = [];

        try {
            $transactions = $manager->start();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $importJob->conversionStatus->setStatus(ConversionStatus::CONVERSION_ERRORED);
            Log::debug('Caught error in start()');
        }

        Log::debug('Past error processing for routine manager');

        if (0 === count($transactions)) {
            Log::error('[a] Zero transactions!');
            $importJob->conversionStatus->setStatus(ConversionStatus::CONVERSION_DONE);
        }
        Log::debug('Grab import job back from manager.');
        $importJob                  = $manager->getImportJob();
        $importJob->setConvertedTransactions($transactions);
        $this->importJob            = $importJob;
        $this->repository->saveToDisk($importJob);

        if (count($transactions) > 0) {
            $importJob->conversionStatus->setStatus(ConversionStatus::CONVERSION_DONE);
        }
        $this->importerAccounts     = $manager->getServiceAccounts();

        return $importJob;
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

    private function startImportFromImportJob(ImportJob $importJob): void
    {
        Log::debug(sprintf('Now at %s', __METHOD__));
        $routine              = new RoutineManager($importJob);

        if (0 === count($importJob->getConvertedTransactions())) {
            $importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_DONE);
            $this->repository->saveToDisk($importJob);
            Log::error('No transactions in array, there is nothing to import.');
            $this->importMessages = $importJob->submissionStatus->messages;
            $this->importWarnings = $importJob->submissionStatus->warnings;
            $this->importErrors   = $importJob->submissionStatus->errors;

            return;
        }

        $importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_RUNNING);

        try {
            $routine->start();
        } catch (ImporterErrorException $e) {
            Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
            $importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_ERRORED);
            $importJob->submissionStatus->addError(0, $e->getMessage());
            $this->repository->saveToDisk($importJob);
            $this->importMessages = $importJob->submissionStatus->messages;
            $this->importWarnings = $importJob->submissionStatus->warnings;
            $this->importErrors   = $importJob->submissionStatus->errors;

            return;
        }


        // set done:
        $importJob->submissionStatus->setStatus(SubmissionStatus::SUBMISSION_DONE);
        $this->importMessages = $importJob->submissionStatus->messages;
        $this->importWarnings = $importJob->submissionStatus->warnings;
        $this->importErrors   = $importJob->submissionStatus->errors;
    }

    private function reportImport(): void
    {
        $list = [
            'info'  => $this->importMessages,
            'warn'  => $this->importWarnings,
            'error' => $this->importErrors,
        ];

        // FIXME this reports to info() which ends up in the result.
        Log::info(sprintf('There are %d message(s)', count($this->importMessages)));
        Log::info(sprintf('There are %d warning(s)', count($this->importWarnings)));
        Log::info(sprintf('There are %d error(s)', count($this->importErrors)));

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

    private function reportBalanceDifferences(ImportJob $importJob): void
    {
        if ('nordigen' !== $importJob->getFlow()) {
            return;
        }
        $configuration = $importJob->getConfiguration();
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
        if (0 !== bccomp($balance->amount, (string)$localAccount->currentBalance)) {
            Log::warning(sprintf('GoCardless balance is %s, Firefly III balance is %s.', $balance->amount, $localAccount->currentBalance));
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: GoCardless reports %s %s, Firefly III reports %s %d', $balance->type, $localAccount->id, $balance->currency, $balance->amount, $localAccount->currencyCode, $localAccount->currentBalance));
        }
        if (0 === bccomp($balance->amount, (string)$localAccount->currentBalance)) {
            $this->line(sprintf('Balance comparison (%s): Firefly III account #%d: Balance OK', $balance->type, $localAccount->id));
        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function importUpload(string $jsonFile, string $importableFile): void
    {
        Log::debug('Start of importUpload');
        $this->repository      = new ImportJobRepository();
        $importJob             = $this->repository->create();

        // do JSON check
        $jsonResult            = $this->verifyJSON($jsonFile);
        if (false === $jsonResult) {
            $message = sprintf('The importer can\'t import %s: could not decode the JSON in config file %s.', $importableFile, $jsonFile);
            Log::error($message);

            return;
        }
        // FIXME this is a hack. Normally, the data importer would know what import flow to use from the user's selection.
        // FIXME but now we parse the config (which we know is valid), take the flow, and give it to the import job.
        $jsonContent           = file_get_contents($jsonFile);
        $json                  = json_decode($jsonContent, true);

        $importableFileContent = '';
        if ('' !== $importableFile && file_exists($importableFile) && is_readable($importableFile)) {
            $importableFileContent = file_get_contents($importableFile);
        }

        $importJob             = $this->repository->setFlow($importJob, $json['flow']);
        $importJob             = $this->repository->setConfigurationString($importJob, $jsonContent);
        $importJob             = $this->repository->setImportableFileString($importJob, $importableFileContent);
        $importJob             = $this->repository->markAs($importJob, 'contains_content');

        // FIXME: this little routine belongs in a function or a helper.
        // FIXME: it is duplicated
        // at this point, also parse and process the uploaded configuration file string.
        $configuration         = Configuration::make();
        if ('' !== $jsonContent && null === $importJob->getConfiguration()) {
            $configuration = Configuration::fromArray(json_decode($jsonContent, true));
        }
        if (null !== $importJob->getConfiguration()) {
            $configuration = $importJob->getConfiguration();
        }
        $configuration->setFlow($importJob->getFlow());
        $importJob->setConfiguration($configuration);
        $this->repository->saveToDisk($importJob);

        Log::debug(sprintf('[b] Going to convert from file "%s" using configuration "%s" and flow "%s".', $importableFile, $jsonFile, $json['flow']));

        // this is it!
        $this->startConversionFromImportJob($importJob);
        $this->reportConversion();

        // crash here if the conversion failed.
        if (0 !== count($this->conversionErrors)) {
            $this->error(sprintf('[b] Too many errors in the data conversion (%d), exit.', count($this->conversionErrors)));

            throw new ImporterErrorException('Too many errors in the data conversion.');
        }

        $this->line(sprintf('Done converting from file %s using configuration %s.', $importableFile, $jsonFile));
        $this->startImportFromImportJob($importJob);
        $this->reportImport();

        $this->line('Done!');
        event(
            new ImportedTransactions(
                basename($jsonFile),
                array_merge($this->conversionMessages, $this->importMessages),
                array_merge($this->conversionWarnings, $this->importWarnings),
                array_merge($this->conversionErrors, $this->importErrors),
                $this->conversionRateLimits
            )
        );
    }

    protected function isNothingDownloaded(): bool
    {
        foreach ($this->conversionErrors as $errors) {
            if (array_any($errors, fn ($error) => str_contains((string)$error, '[a111]'))) {
                return true;
            }
        }

        return false;
    }

    protected function isExpiredAgreement(): bool
    {
        foreach ($this->conversionErrors as $errors) {
            if (array_any($errors, fn ($error) => str_contains((string)$error, 'EUA') && str_contains((string)$error, 'expired'))) {
                return true;
            }
        }

        return false;
    }
}
