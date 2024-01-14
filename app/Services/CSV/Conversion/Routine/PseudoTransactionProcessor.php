<?php
/*
 * PseudoTransactionProcessor.php
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

namespace App\Services\CSV\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Conversion\Task\AbstractTask;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Conversion\ProgressInformation;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetCurrencyRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetPreferenceRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use GrumpyDictator\FFIIIApiSupport\Response\GetCurrencyResponse;
use GrumpyDictator\FFIIIApiSupport\Response\PreferenceResponse;

/**
 * Class PseudoTransactionProcessor
 */
class PseudoTransactionProcessor
{
    use ProgressInformation;

    private Account             $defaultAccount;
    private TransactionCurrency $defaultCurrency;
    private array               $tasks;

    /**
     * PseudoTransactionProcessor constructor.
     *
     * @throws ImporterErrorException
     */
    public function __construct(?int $defaultAccountId)
    {
        $this->tasks = config('csv.transaction_tasks');
        $this->getDefaultAccount($defaultAccountId);
        $this->getDefaultCurrency();
    }

    public function processPseudo(array $lines): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $count     = count($lines);
        $processed = [];
        app('log')->info(sprintf('Converting %d line(s) into transactions.', $count));

        /** @var array $line */
        foreach ($lines as $index => $line) {
            app('log')->info(sprintf('Now processing line %d/%d.', $index + 1, $count));
            $processed[] = $this->processPseudoLine($line);
            // $this->addMessage($index, sprintf('Converted CSV line %d into a transaction.', $index + 1));
        }
        app('log')->info(sprintf('Done converting %d line(s) into transactions.', $count));

        return $processed;
    }

    /**
     * @throws ImporterErrorException
     */
    private function getDefaultAccount(?int $accountId): void
    {
        $url   = SecretManager::getBaseUrl();
        $token = SecretManager::getAccessToken();

        if (null !== $accountId) {
            $accountRequest       = new GetAccountRequest($url, $token);
            $accountRequest->setVerify(config('importer.connection.verify'));
            $accountRequest->setTimeOut(config('importer.connection.timeout'));
            $accountRequest->setId($accountId);

            // @var GetAccountResponse $result
            try {
                $result = $accountRequest->get();
            } catch (ApiHttpException $e) {
                app('log')->error($e->getMessage());

                throw new ImporterErrorException(sprintf('The default account in your configuration file (%d) does not exist.', $accountId));
            }
            $this->defaultAccount = $result->getAccount();
        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function getDefaultCurrency(): void
    {
        $url             = SecretManager::getBaseUrl();
        $token           = SecretManager::getAccessToken();

        $prefRequest     = new GetPreferenceRequest($url, $token);
        $prefRequest->setVerify(config('importer.connection.verify'));
        $prefRequest->setTimeOut(config('importer.connection.timeout'));
        $prefRequest->setName('currencyPreference');

        try {
            /** @var PreferenceResponse $response */
            $response = $prefRequest->get();
        } catch (ApiHttpException $e) {
            app('log')->error($e->getMessage());

            throw new ImporterErrorException('Could not load the users currency preference.');
        }
        $code            = $response->getPreference()->stringData ?? 'EUR';
        $currencyRequest = new GetCurrencyRequest($url, $token);
        $currencyRequest->setVerify(config('importer.connection.verify'));
        $currencyRequest->setTimeOut(config('importer.connection.timeout'));
        $currencyRequest->setCode($code);

        try {
            /** @var GetCurrencyResponse $result */
            $result                = $currencyRequest->get();
            $this->defaultCurrency = $result->getCurrency();
        } catch (ApiHttpException $e) {
            app('log')->error($e->getMessage());

            throw new ImporterErrorException(sprintf('The default currency ("%s") could not be loaded.', $code));
        }
    }

    private function processPseudoLine(array $line): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        foreach ($this->tasks as $task) {
            /** @var AbstractTask $object */
            $object = app($task);
            app('log')->debug(sprintf('Now running task %s', $task));
            if ($object->requiresDefaultAccount()) {
                $object->setAccount($this->defaultAccount);
            }
            if ($object->requiresTransactionCurrency()) {
                $object->setTransactionCurrency($this->defaultCurrency);
            }

            $line   = $object->process($line);
        }
        app('log')->debug('Final transaction: ', $line);

        return $line;
    }
}
