<?php
/*
 * TransactionProcessor.php
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

namespace App\Services\Nordigen\Conversion\Routine;

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Request\GetTransactionsRequest;
use App\Services\Nordigen\Response\GetTransactionsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Carbon\Carbon;

/**
 * Class TransactionProcessor
 */
class TransactionProcessor
{
    use ProgressInformation;

    /** @var string */
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private Configuration $configuration;
    private ?Carbon       $notAfter;
    private ?Carbon       $notBefore;

    /**
     * @return array
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));
        $this->notBefore = null;
        $this->notAfter  = null;
        if ('' !== $this->configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($this->configuration->getDateNotBefore());
        }


        if ('' !== $this->configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($this->configuration->getDateNotAfter());
        }

        $accounts = array_keys($this->configuration->getAccounts());

        $return = [];
        foreach ($accounts as $key => $account) {
            $account = (string) $account;
            app('log')->debug(sprintf('Going to download transactions for account #%d "%s"', $key, $account));

            app('log')->debug(sprintf('Will also download information on the account for debug purposes.'));
            $object = new Account();
            $object->setIdentifier($account);
            try {
                AccountInformationCollector::collectInformation($object);
            } catch (AgreementExpiredException $e) {
                $this->addError(0, 'Your Nordigen End User Agreement has expired. You must refresh it by generating a new one through the Firefly III user interface. See the other error messages for more information.');
                if (array_key_exists('summary', $e->json) && '' !== (string) $e->json['summary']) {
                    $this->addError(0, $e->json['summary']);
                }
                if (array_key_exists('detail', $e->json) && '' !== (string) $e->json['detail']) {
                    $this->addError(0, $e->json['detail']);
                }

                return [];
            }
            app('log')->debug(sprintf('Done downloading information for debug purposes.'));

            $accessToken = TokenManager::getAccessToken();
            $url         = config('nordigen.url');
            $request     = new GetTransactionsRequest($url, $accessToken, $account);
            $request->setTimeOut(config('importer.connection.timeout'));
            /** @var GetTransactionsResponse $transactions */
            try {
                $transactions = $request->get();
            } catch (ImporterHttpException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
            $return[$account] = $this->filterTransactions($transactions);
            app('log')->debug(sprintf('Done downloading transactions for account %s "%s"', $key, $account));
        }
        app('log')->debug('Done with download');

        return $return;
    }

    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @param GetTransactionsResponse $transactions
     * @return array
     */
    private function filterTransactions(GetTransactionsResponse $transactions): array
    {
        app('log')->info(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));
        if (null !== $this->notBefore) {
            app('log')->info(sprintf('Will not grab transactions before "%s"', $this->notBefore->format('Y-m-d H:i:s')));
        }
        if (null !== $this->notAfter) {
            app('log')->info(sprintf('Will not grab transactions after "%s"', $this->notAfter->format('Y-m-d H:i:s')));
        }
        $return = [];
        foreach ($transactions as $transaction) {
            $madeOn = $transaction->getDate();

            if (null !== $this->notBefore && $madeOn->lt($this->notBefore)) {
                app('log')->debug(
                    sprintf(
                        'Skip transaction because "%s" is before "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notBefore->format(self::DATE_TIME_FORMAT)
                    )
                );
                continue;
            }
            if (null !== $this->notAfter && $madeOn->gt($this->notAfter)) {
                app('log')->debug(
                    sprintf(
                        'Skip transaction because "%s" is after "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notAfter->format(self::DATE_TIME_FORMAT)
                    )
                );

                continue;
            }
            app('log')->debug(sprintf('Include transaction because date is "%s".', $madeOn->format(self::DATE_TIME_FORMAT),));
            $return[] = $transaction;
        }
        app('log')->info(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

}
