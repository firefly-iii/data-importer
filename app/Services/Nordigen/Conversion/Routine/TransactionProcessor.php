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
use App\Exceptions\RateLimitException;
use App\Services\Nordigen\Model\Account;
use App\Services\Nordigen\Request\GetTransactionsRequest;
use App\Services\Nordigen\Response\GetTransactionsResponse;
use App\Services\Nordigen\Services\AccountInformationCollector;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class TransactionProcessor
 */
class TransactionProcessor
{
    use ProgressInformation;

    /** @var string */
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private array         $accounts;
    private Configuration $configuration;
    private ?Carbon       $notAfter       = null;
    private ?Carbon       $notBefore      = null;

    /**
     * @throws ImporterErrorException
     */
    public function download(): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $this->notBefore = null;
        $this->notAfter  = null;
        $this->accounts  = [];
        if ('' !== $this->configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($this->configuration->getDateNotBefore());
        }

        if ('' !== $this->configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($this->configuration->getDateNotAfter());
        }

        $accounts        = array_keys($this->configuration->getAccounts());

        $return          = [];
        Log::debug(sprintf('Found %d accounts to download from.', count($accounts)));
        $total    = count($accounts);
        foreach ($accounts as $key => $account) {
            $account                    = (string) $account;
            Log::debug(sprintf('[%d/%d] Going to download transactions for account #%d "%s"', $key+1, $total, $key+1, $account));
            $object                     = new Account();
            $object->setIdentifier($account);
            $fullInfo                   = null;

            try {
                $fullInfo = AccountInformationCollector::collectInformation($object, false);
            } catch (AgreementExpiredException $e) {
                $this->addError(
                    0,
                    '[a113]: Your GoCardless End User Agreement has expired. You must refresh it by generating a new one through the Firefly III Data Importer user interface. See the other error messages for more information.'
                );
                if (array_key_exists('summary', $e->json) && '' !== (string) $e->json['summary']) {
                    $this->addError(0, $e->json['summary']);
                }
                if (array_key_exists('detail', $e->json) && '' !== (string) $e->json['detail']) {
                    $this->addError(0, $e->json['detail']);
                }
                $return[$account] = [];

                continue;
            }
            Log::debug('Done downloading information for debug purposes.');
            $this->accounts[]           = $fullInfo;

            try {
                $accessToken = TokenManager::getAccessToken();
            } catch (ImporterErrorException $e) {
                $this->addError(0, $e->getMessage());
                $return[$account] = [];

                continue;
            }
            $url                        = config('nordigen.url');
            $request                    = new GetTransactionsRequest($url, $accessToken, $account);
            $request->setTimeOut(config('importer.connection.timeout'));

            /** @var GetTransactionsResponse $transactions */
            try {
                $transactions = $request->get();
                Log::debug(sprintf('GetTransactionsResponse: count %d transaction(s)', count($transactions)));
            } catch (ImporterHttpException|RateLimitException $e) {
                Log::debug(sprintf('Ran into %s instead of GetTransactionsResponse', $e::class));
                $this->addWarning(0, $e->getMessage());
                $return[$account]           = [];

                // save the rate limits:
                $this->rateLimits[$account] = [
                    'remaining' => $request->getRemaining(),
                    'reset'     => $request->getReset(),
                ];

                continue;
            } catch (AgreementExpiredException $e) {
                Log::debug(sprintf('Ran into %s instead of GetTransactionsResponse', $e::class));
                // agreement expired, whoops.
                $return[$account]           = [];
                $this->addError(0, $e->json['detail'] ?? '[a114]: Your EUA has expired.');
                // save rate limits, even though they may not be there.
                $this->rateLimits[$account] = [
                    'remaining' => $request->getRemaining(),
                    'reset'     => $request->getReset(),
                ];

                continue;
            }
            $this->rateLimits[$account] = [
                'remaining' => $request->getRemaining(),
                'reset'     => $request->getReset(),
            ];

            $return[$account]           = $this->filterTransactions($transactions);
            Log::debug(sprintf('[%d/%d] Done downloading transactions for account #%d "%s"', $key+1, $total, $key+1, $account));
        }
        Log::debug('Done with download of transactions.');

        return $return;
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    private function filterTransactions(GetTransactionsResponse $transactions): array
    {
        Log::info(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));
        if ($this->notBefore instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions before "%s"', $this->notBefore->format('Y-m-d H:i:s')));
        }
        if ($this->notAfter instanceof Carbon) {
            Log::info(sprintf('Will not grab transactions after "%s"', $this->notAfter->format('Y-m-d H:i:s')));
        }
        $return = [];
        foreach ($transactions as $transaction) {
            $madeOn   = $transaction->getDate();

            if ($this->notBefore instanceof Carbon && $madeOn->lt($this->notBefore)) {
                Log::debug(
                    sprintf(
                        'Skip transaction because "%s" is before "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notBefore->format(self::DATE_TIME_FORMAT)
                    )
                );

                continue;
            }
            if ($this->notAfter instanceof Carbon && $madeOn->gt($this->notAfter)) {
                Log::debug(
                    sprintf(
                        'Skip transaction because "%s" is after "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notAfter->format(self::DATE_TIME_FORMAT)
                    )
                );

                continue;
            }
            // add error if amount is zero:
            if (0 === bccomp('0', $transaction->transactionAmount)) {
                $this->addWarning(0, sprintf(
                    'Transaction #%s ("%s", "%s", "%s") has an amount of zero and has been ignored..',
                    $transaction->transactionId,
                    $transaction->getSourceName(),
                    $transaction->getDestinationName(),
                    $transaction->getDescription()
                ));
                Log::debug(sprintf('Skip transaction because amount is zero: "%s".', $transaction->transactionAmount));

                continue;
            }

            Log::debug(sprintf('Include transaction because date is "%s".', $madeOn->format(self::DATE_TIME_FORMAT)));

            $return[] = $transaction;
        }
        Log::info(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }
}
