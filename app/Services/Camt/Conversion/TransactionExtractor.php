<?php

namespace App\Services\Camt\Conversion;

use App\Services\Camt\Transaction;
use App\Services\Shared\Configuration\Configuration;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\DTO\Message;

class TransactionExtractor
{
    private Configuration $configuration;

    /**
     * @param  Configuration  $configuration
     */
    public function __construct(Configuration $configuration)
    {
        app('log')->debug('Now in TransactionExtractor.');
        $this->configuration = $configuration;
    }

    /**
     * @param  Message  $message
     * @return array
     */
    public function extractTransactions(Message $message): array
    {
        app('log')->debug('Now in extractTransactions.');
        // get transactions from XML file
        $transactions = [];
        $statements   = $message->getRecords();
        /** @var CamtStatement $statement */
        foreach ($statements as $statement) { // -> Level B
            $entries = $statement->getEntries();
            foreach ($entries as $entry) {                       // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, []);
                }
                if (0 !== $count) {
                    $handling = $this->configuration->getGroupedTransactionHandling();
                    if ('split' === $handling) {
                        $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, $entry->getTransactionDetails());
                    }
                    if ('single' === $handling) {
                        foreach ($entry->getTransactionDetails() as $detail) {
                            $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, [$detail]);
                        }
                    }
                    if ('group' === $handling) {
                        $transactions[] = new Transaction($this->configuration, $message, $statement, $entry, []);
                    }
                }
            }
        }
        app('log')->debug(sprintf('Extracted %d transaction(s)', count($transactions)));

        return $transactions;
    }

}
