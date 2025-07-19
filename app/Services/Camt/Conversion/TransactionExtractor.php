<?php

declare(strict_types=1);

namespace App\Services\Camt\Conversion;

use App\Services\Camt\Transaction;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\DTO\Message;
use Illuminate\Support\Facades\Log;

class TransactionExtractor
{
    use ProgressInformation;

    public function __construct(private Configuration $configuration)
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
    }

    public function extractTransactions(Message $message): array
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        // get transactions from XML file
        $transactions = [];
        $statements   = $message->getRecords();
        $totalCount   = count($statements);

        /**
         * @var int           $index
         * @var CamtStatement $statement
         */
        foreach ($statements as $i => $statement) { // -> Level B
            $entries    = $statement->getEntries();
            $entryCount = count($entries);
            Log::debug(sprintf('[%s] [%d/%d] Now working on statement with %d entries.', config('importer.version'), $i + 1, $totalCount, $entryCount));
            foreach ($entries as $ii => $entry) {                // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                Log::debug(sprintf('[%s] [%d/%d] Now working on entry with %d detail entries.', config('importer.version'), $ii + 1, $entryCount, $count));
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($message, $statement, $entry, []);
                }
                if (0 !== $count) {
                    $handling = $this->configuration->getGroupedTransactionHandling();
                    if ('split' === $handling) {
                        $transactions[] = new Transaction( $message, $statement, $entry, $entry->getTransactionDetails());
                    }
                    if ('single' === $handling) {
                        foreach ($entry->getTransactionDetails() as $detail) {
                            $transactions[] = new Transaction( $message, $statement, $entry, [$detail]);
                        }
                    }
                    if ('group' === $handling) {
                        if (1 === $count) {
                            $transactions[] = new Transaction( $message, $statement, $entry, $entry->getTransactionDetails());
                        }
                        if ($count > 1) {
                            $transactions[] = new Transaction( $message, $statement, $entry, []);
                        }
                    }
                }
                Log::debug(sprintf('[%s] [%d/%d] Done working on entry with %d detail entries.', config('importer.version'), $ii + 1, $entryCount, $count));
            }
        }
        Log::debug(sprintf('Extracted %d transaction(s)', count($transactions)));

        return $transactions;
    }
}
