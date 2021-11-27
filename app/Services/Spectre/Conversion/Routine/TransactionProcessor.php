<?php
declare(strict_types=1);

namespace App\Services\Spectre\Conversion\Routine;

use App\Exceptions\ImporterHttpException;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Spectre\Request\GetTransactionsRequest;
use App\Services\Spectre\Request\PutRefreshConnectionRequest;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\GetTransactionsResponse;
use Carbon\Carbon;
use Log;

/**
 * Class TransactionProcessor
 */
class TransactionProcessor
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    private Configuration $configuration;
    private string        $downloadIdentifier;
    private ?Carbon       $notAfter;
    private ?Carbon       $notBefore;

    /**
     * @return array
     */
    public function download(): array
    {
        $this->refreshConnection();
        $this->notBefore = null;
        $this->notAfter  = null;
        if ('' !== (string) $this->configuration->getDateNotBefore()) {
            $this->notBefore = new Carbon($this->configuration->getDateNotBefore());
        }

        if ('' !== (string) $this->configuration->getDateNotAfter()) {
            $this->notAfter = new Carbon($this->configuration->getDateNotAfter());
        }

        Log::debug('Now in download()');
        $accounts = array_keys($this->configuration->getAccounts());
        Log::debug(sprintf('Found %d accounts to download from.', count($this->configuration->getAccounts())));
        $return = [];
        foreach ($accounts as $account) {
            $account = (string) $account;
            Log::debug(sprintf('Going to download transactions for account #%s', $account));
            $url                   = config('spectre.url');
            $appId                 = config('spectre.app_id');
            $secret                = config('spectre.secret');
            $request               = new GetTransactionsRequest($url, $appId, $secret);
            $request->accountId    = $account;
            $request->connectionId = $this->configuration->getConnection();
            /** @var GetTransactionsResponse $transactions */
            $transactions = $request->get();
            /*
             * Getting a Response object means that the Transaction objects are basically cast back into an array making this
             * exercise pretty pointless (from array to object back to array).
             *
             * Does mean however that we can normalise the data before we start using it.
             */
            $return[$account] = $this->filterTransactions($transactions);
        }
        $final = [];
        foreach ($return as $set) {
            $final = array_merge($final, $set);
        }

        return $final;
    }

    /**
     * @throws ImporterHttpException
     */
    private function refreshConnection(): void
    {
        // refresh connection
        $url    = config('spectre.url');
        $appId  = config('spectre.app_id');
        $secret = config('spectre.secret');
        $put    = new PutRefreshConnectionRequest($url, $appId, $secret);
        $put->setConnection($this->configuration->getConnection());
        $response = $put->put();
        if ($response instanceof ErrorResponse) {
            Log::alert('Could not refresh connection.');
            Log::alert(sprintf('%s: %s', $response->class, $response->message));
        }
    }

    /**
     * @param GetTransactionsResponse $transactions
     */
    private function filterTransactions(GetTransactionsResponse $transactions): array
    {
        Log::debug(sprintf('Going to filter downloaded transactions. Original set length is %d', count($transactions)));
        if (null !== $this->notBefore) {
            Log::debug(sprintf('Will not grab transactions before "%s"', $this->notBefore->format('Y-m-d H:i:s')));
        }
        if (null !== $this->notAfter) {
            Log::debug(sprintf('Will not grab transactions after "%s"', $this->notAfter->format('Y-m-d H:i:s')));
        }
        $return = [];
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $madeOn = $transaction->madeOn;

            if (null !== $this->notBefore && $madeOn->lte($this->notBefore)) {
                app('log')->info(
                    sprintf(
                        'Skip transaction because "%s" is before "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notBefore->format(self::DATE_TIME_FORMAT)
                    )
                );
                continue;
            }
            if (null !== $this->notAfter && $madeOn->gte($this->notAfter)) {
                app('log')->info(
                    sprintf(
                        'Skip transaction because "%s" is after "%s".',
                        $madeOn->format(self::DATE_TIME_FORMAT),
                        $this->notAfter->format(self::DATE_TIME_FORMAT)
                    )
                );

                continue;
            }
            app('log')->info(sprintf('Include transaction because date is "%s".', $madeOn->format(self::DATE_TIME_FORMAT),));
            $return[] = $transaction->toArray();
        }
        Log::debug(sprintf('After filtering, set is %d transaction(s)', count($return)));

        return $return;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $downloadIdentifier
     */
    public function setDownloadIdentifier(string $downloadIdentifier): void
    {
        $this->downloadIdentifier = $downloadIdentifier;
    }

}
