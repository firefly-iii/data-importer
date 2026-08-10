<?php

declare(strict_types=1);

namespace App\Services\Akahu\Conversion;

use App\Models\ImportJob;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Akahu\Request\GetTransactionsRequest;
use App\Services\Akahu\Request\AkahuDateRange;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\CreatesAccounts;
use App\Repository\ImportJob\ImportJobRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class RoutineManager implements RoutineManagerInterface
{
    use CreatesAccounts;

    private ImportJob $importJob;

    private const int CREATE_ACCOUNT_SENTINEL_ID = 0;

    public function __construct(ImportJob $importJob)
    {
        $this->importJob = $importJob;
        $this->repository = new ImportJobRepository();
        $this->setExistingServiceAccounts($importJob->getServiceAccounts());
    }

    public function getServiceAccounts(): array
    {
        return $this->importJob->getServiceAccounts();
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function start(): array
    {
        $configuration = $this->importJob->getConfiguration();
        $accounts = $configuration->getAccounts();
        $transactions = [];

        foreach($accounts as $akahuAccountId => $maybeFireflyAccountId) {
            $fireflyAccountId = $this->ensureFireflyAccountId($maybeFireflyAccountId, $akahuAccountId);
            $dateRange = new AkahuDateRange($configuration);

            $fetcher = new TransactionFetcher(
                $akahuAccountId,
                $fireflyAccountId,
                $dateRange,
                $this->importJob
            );
            $converter = new TransactionConverter($fetcher);
            $accountTransactions = $converter->convert();

            array_push($transactions, ...$accountTransactions);
        }

        return $transactions;
    }

    private function ensureFireflyAccountId(int $fireflyAccountId, string $akahuAccountId): int
    {
        if (self::CREATE_ACCOUNT_SENTINEL_ID === $fireflyAccountId) {
            Log::debug(sprintf('Creating new account for account "%s".', $akahuAccountId));
            return $this->createNewAccount($akahuAccountId);
        }

        return $fireflyAccountId;
    }
}
