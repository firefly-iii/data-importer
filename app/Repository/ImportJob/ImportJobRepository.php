<?php

declare(strict_types=1);
/*
 * ImportJobRepository.php
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

namespace App\Repository\ImportJob;

use App\Exceptions\ImporterErrorException;
use App\Models\ImportJob;
use App\Services\CSV\Mapper\TransactionCurrencies;
use App\Services\LunchFlow\Validation\NewJobDataCollector as LunchFlowNewJobDataCollector;
use App\Services\Nordigen\Validation\NewJobDataCollector as NordigenNewJobDataCollector;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\File\FileContentSherlock;
use App\Services\SimpleFIN\Validation\NewJobDataCollector as SimpleFINNewJobDataCollector;
use Exception;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;

class ImportJobRepository
{
    public function create(): ImportJob
    {
        $importJob = ImportJob::createNew();

        // save to disk:
        $this->saveToDisk($importJob);
        Log::debug(sprintf('Created new import job with key "%s"', $importJob->identifier));

        return $importJob;
    }

    public function deleteImportJob(ImportJob $importJob): void
    {
        $disk = $this->getDisk();
        $file = sprintf('%s.json', $importJob->identifier);
        if (!$disk->exists($file)) {
            return;
        }
        Log::warning(sprintf('Deleted import job with key "%s"', $importJob->identifier));
        $disk->delete($file);
    }

    public function find(string $identifier): ImportJob
    {
        $disk    = $this->getDisk();
        $file    = sprintf('%s.json', $identifier);
        if (!$disk->exists($file)) {
            throw new ImporterErrorException(sprintf('There is no import job with identifier "%s".', $identifier));
        }
        $content = $disk->get($file);
        if (null === $content || '' === $content) {
            throw new ImporterErrorException(sprintf('The file for import job "%s" is empty.', $identifier));
        }
        // Log::debug(sprintf('Found import job with identifier "%s"', $identifier));

        return ImportJob::createFromJson($content);
    }

    public function saveToDisk(ImportJob $importJob): void
    {
        $disk = $this->getDisk();
        $path = sprintf('%s.json', $importJob->identifier);
        if ($disk->exists($path)) {
            $content = trim((string)$disk->get($path));
            if ('' !== $content) {
                $valid = json_validate($content);
                if ($valid) {
                    $json               = json_decode($content, true);
                    $oldInstanceCounter = $json['instance_counter'];
                    $newInstanceCounter = $importJob->getInstanceCounter();
                    if ($oldInstanceCounter > $newInstanceCounter) {
                        throw new ImporterErrorException(sprintf('Cowardly refuse to overwrite older (#%d) import job file with newer (#%d).', $oldInstanceCounter, $newInstanceCounter));
                    }
                }
            }
        }
        Log::debug(sprintf('Saved import job with key "%s" to disk.', $importJob->identifier));
        $disk->put($path, $importJob->toString());
    }

    public function markAs(ImportJob $importJob, string $state): ImportJob
    {
        $importJob->setState($state);
        $this->saveToDisk($importJob);

        return $importJob;
    }

    /**
     * FIXME: this is starting to look like a "catch-all" function for all tiny details that need to be taken care of when a new import is started.
     *
     * @throws ApiHttpException
     */
    public function parseImportJob(ImportJob $importJob): MessageBag
    {
        Log::debug(sprintf('Now in parseImportJob("%s")', $importJob->identifier));
        if (true === $importJob->isInitialized()) {
            Log::debug('Import job is already initialized, do nothing.');
            return new MessageBag();
        }
        $messageBag    = new MessageBag();
        $configuration = $importJob->getConfiguration();

        // collect Firefly III accounts, if not already in place for this job.
        // this function returns an array with keys 'assets' and 'liabilities', each containing an array of Firefly III accounts.

        $allAccounts   = $importJob->getApplicationAccounts();
        $count         = count($allAccounts[Constants::ASSET_ACCOUNTS] ?? []) + count($allAccounts[Constants::LIABILITIES] ?? []);
        if (0 === $count) {
            Log::debug('No asset accounts or liabilities found, will collect them now.');
            $applicationAccounts = $this->getApplicationAccounts();
            $importJob->setApplicationAccounts($applicationAccounts);
        }
        if (0 === count($importJob->getCurrencies())) {
            $currencies = $this->getCurrencies();
            $importJob->setCurrencies($currencies);
        }


        Log::debug(sprintf('Now in flow("%s")', $importJob->getFlow()));

        // FIXME this routine must also be followed when doing uploads and POST and what not.
        // FIXME aka this should be part of the routine.
        // validate stuff (from simplefin etc).
        switch ($importJob->getFlow()) {
            case 'file':
                // do file content sherlock things.
                $detector      = new FileContentSherlock();
                $content       = $importJob->getImportableFileString($configuration->isConversion());
                $fileType      = $detector->detectContentTypeFromContent($content);
                $configuration->setContentType($fileType);
                if ('camt' === $fileType) {
                    $camtType = $detector->getCamtType();
                    $configuration->setCamtType($camtType);
                }

                break;

            case 'lunchflow':
                $validator     = new LunchFlowNewJobDataCollector();
                $validator->setImportJob($importJob);
                $messageBag    = $validator->collectAccounts();
                // get import job + configuration back:
                $importJob     = $validator->getImportJob();
                $configuration = $importJob->getConfiguration();
                $configuration->setDuplicateDetectionMethod('cell');

                break;

            case 'simplefin':
                $validator     = new SimpleFINNewJobDataCollector();
                $validator->setImportJob($importJob);
                $messageBag    = $validator->collectAccounts();
                // get import job + configuration back:
                $importJob     = $validator->getImportJob();
                $configuration = $importJob->getConfiguration();
                $configuration->setDuplicateDetectionMethod('cell');

                break;

            case 'nordigen':
                // nordigen, download list of accounts.
                $validator     = new NordigenNewJobDataCollector();
                $validator->setImportJob($importJob);
                $messageBag    = $validator->collectAccounts();
                // get import job + configuration back:
                $importJob     = $validator->getImportJob();
                $configuration = $importJob->getConfiguration();
                $configuration->setDuplicateDetectionMethod('cell');

                break;

            case 'sophtron':
                // get import job + configuration back:
                $configuration = $importJob->getConfiguration();
                $configuration->setDuplicateDetectionMethod('cell');

                break;

            default:
                $messageBag->add('importable_file', sprintf('Cannot yet process import flow "%s"', $importJob->getFlow()));
                $messageBag->add('config_file', sprintf('Cannot yet process import flow "%s"', $importJob->getFlow()));
        }

        // save configuration and return it.
        if (0 === count($messageBag)) {
            $importJob->setState('is_parsed');
            $importJob->setInitialized(true);
        }
        $importJob     = $this->setConfiguration($importJob, $configuration);
        $this->saveToDisk($importJob);

        // if parse errors, display to user with a redirect to upload?
        return $messageBag;
    }

    public static function convertString(string $content): string
    {
        $encoding = mb_detect_encoding($content, config('importer.encoding'), true);
        if (false === $encoding) {
            Log::warning('Tried to detect encoding but could not find valid encoding. Assume UTF-8.');

            return $content;
        }
        if ('ASCII' === $encoding || 'UTF-8' === $encoding) {
            return $content;
        }
        Log::warning(sprintf('Content is detected as "%s" and will be converted to UTF-8. Your milage may vary.', $encoding));

        return mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    public function setConfigurationString(ImportJob $importJob, string $configFileContent): ImportJob
    {
        $importJob->setConfigurationString($configFileContent);
        $this->saveToDisk($importJob);

        return $importJob;
    }

    public function setFlow(ImportJob $importJob, string $flow): ImportJob
    {
        $importJob->setFlow($flow);
        $this->saveToDisk($importJob);

        return $importJob;
    }

    public function setImportableFileString(ImportJob $importJob, string $importableFileContent)
    {
        $importJob->setImportableFileString($importableFileContent);
        $this->saveToDisk($importJob);

        return $importJob;
    }

    private function getDisk(): Filesystem|LocalFilesystemAdapter
    {
        return Storage::disk('import-jobs');
    }

    private function setConfiguration(ImportJob $importJob, Configuration $configuration): ImportJob
    {
        $importJob->setConfiguration($configuration);
        $this->saveToDisk($importJob);

        return $importJob;
    }

    /**
     * @throws ApiHttpException
     */
    private function getApplicationAccounts(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $accounts = [
            Constants::ASSET_ACCOUNTS => [],
            Constants::LIABILITIES    => [],
        ];
        $url      = null;

        try {
            $url           = SecretManager::getBaseUrl();
            $token         = SecretManager::getAccessToken();

            if ('' === $url || '' === $token) {
                Log::error('Base URL or Access Token is empty. Cannot fetch accounts.', ['url_empty' => '' === $url, 'token_empty' => '' === $token]);

                return $accounts; // Return empty accounts if auth details are missing
            }

            // Fetch ASSET accounts
            Log::debug('Fetching asset accounts from Firefly III.', ['url' => $url]);
            $requestAsset  = new GetAccountsRequest($url, $token);
            $requestAsset->setType(GetAccountsRequest::ASSET);
            $requestAsset->setVerify(config('importer.connection.verify'));
            $requestAsset->setTimeOut(config('importer.connection.timeout'));
            $responseAsset = $requestAsset->get();

            /** @var Account $account */
            foreach ($responseAsset as $account) {
                // Log::debug(sprintf('Class of account is %s', get_class($account)));
                $accounts[Constants::ASSET_ACCOUNTS][$account->id] = $account;
            }
            Log::debug(sprintf('Fetched %d asset accounts.', count($accounts[Constants::ASSET_ACCOUNTS])));
        } catch (ApiHttpException|Exception $e) {
            Log::error(sprintf('%s while fetching Firefly III asset accounts.', get_class($e)), [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'url'     => $url, // Log URL that might have caused issue
                'trace'   => $e->getTraceAsString(),
            ]);

        }

        try {
            Log::debug('Fetching liability accounts from Firefly III.', ['url' => $url]);
            $requestLiability  = new GetAccountsRequest($url, $token);
            $requestLiability->setVerify(config('importer.connection.verify'));
            $requestLiability->setTimeOut(config('importer.connection.timeout'));
            $requestLiability->setType(GetAccountsRequest::LIABILITIES);
            $responseLiability = $requestLiability->get();

            /** @var Account $account */
            foreach ($responseLiability as $account) {
                $accounts[Constants::LIABILITIES][$account->id] = $account;
            }
            Log::debug(sprintf('Fetched %d liability accounts.', count($accounts[Constants::LIABILITIES])));

        } catch (ApiHttpException|Exception $e) {
            Log::error(sprintf('%s while fetching Firefly III liability accounts.', get_class($e)), [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'url'     => $url,
                'trace'   => $e->getTraceAsString(),
            ]);
        }
        // Log::debug('CollectsAccounts::getFireflyIIIAccounts - Returning accounts structure.', $accounts);

        return $accounts;
    }

    private function getCurrencies(): array
    {
        try {
            /** @var TransactionCurrencies $mapper */
            $mapper = app(TransactionCurrencies::class);

            return $mapper->getMap();
        } catch (Exception $e) {
            Log::error(sprintf('Failed to load currencies: %s', $e->getMessage()));

            return [];
        }
    }
}
