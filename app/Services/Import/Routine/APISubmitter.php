<?php
declare(strict_types=1);
/**
 * APISubmitter.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\Import\Routine;

use App\Services\CSV\Configuration\Configuration;
use App\Services\Import\Support\ProgressInformation;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Transaction;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionGroup;
use GrumpyDictator\FFIIIApiSupport\Request\GetSearchTransactionsRequest;
use GrumpyDictator\FFIIIApiSupport\Request\PostTagRequest;
use GrumpyDictator\FFIIIApiSupport\Request\PostTransactionRequest;
use GrumpyDictator\FFIIIApiSupport\Request\PutTransactionRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetTransactionsResponse;
use GrumpyDictator\FFIIIApiSupport\Response\PostTagResponse;
use GrumpyDictator\FFIIIApiSupport\Response\PostTransactionResponse;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;
use Log;

/**
 * Class APISubmitter
 */
class APISubmitter
{
    use ProgressInformation;

    private string        $tag;
    private string        $tagDate;
    private bool          $addTag;
    private string        $vanityURL;
    private Configuration $configuration;

    /**
     * @param array $lines
     */
    public function processTransactions(array $lines): void
    {
        $this->tag     = sprintf('CSV Import on %s', date('Y-m-d \@ H:i'));
        $this->tagDate = date('Y-m-d');
        $count         = count($lines);
        Log::info(sprintf('Going to submit %d transactions to your Firefly III instance.', $count));

        $this->vanityURL = Token::getVanityURL();

        Log::debug(sprintf('Vanity URL : "%s"', $this->vanityURL));

        // create the tag, to be used later on.
        $this->createTag();

        /**
         * @var int   $index
         * @var array $line
         */
        foreach ($lines as $index => $line) {
            Log::debug(sprintf('Now submitting transaction %d/%d', ($index+1), $count));
            // first do local duplicate transaction check (the "cell" method):
            $unique = $this->uniqueTransaction($index, $line);
            if (true === $unique) {
                Log::debug(sprintf('Transaction #%d is unique.', $index+1));
                $groupInfo = $this->processTransaction($index, $line);
                $this->addTagToGroups($groupInfo);
            }
            if(false === $unique) {
                Log::debug(sprintf('Transaction #%d is NOT unique.', $index+1));
            }
        }
        Log::info(sprintf('Done submitting %d transactions to your Firefly III instance.', $count));
    }

    /**
     *
     */
    private function createTag(): void
    {
        if (false === $this->addTag) {
            Log::debug('Not instructed to add a tag, so will not create one.');

            return;
        }
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new PostTagRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $body = [
            'tag'  => $this->tag,
            'date' => $this->tagDate,
        ];
        $request->setBody($body);

        try {
            /** @var PostTagResponse $response */
            $response = $request->post();
        } catch (ApiHttpException $e) {
            $message = sprintf('Could not create tag. %s', $e->getMessage());
            Log::error($message);
            Log::error($e->getTraceAsString());
            $this->addError(0, $message);

            return;
        }
        if ($response instanceof ValidationErrorResponse) {
            Log::error(json_encode($response->errors->toArray()));

            return;
        }
        if (null !== $response->getTag()) {
            Log::info(sprintf('Created tag #%d "%s"', $response->getTag()->id, $response->getTag()->tag));
        }
    }

    /**
     * Verify if the transaction is unique, based on the configuration
     * and the content of the transaction. Returns a boolean.
     *
     * @param int   $index
     * @param array $line
     *
     * @return bool
     */
    private function uniqueTransaction(int $index, array $line): bool
    {
        if ('cell' !== $this->configuration->getDuplicateDetectionMethod()) {
            Log::debug(
                sprintf('Duplicate detection method is "%s", so this method is skipped (return true).', $this->configuration->getDuplicateDetectionMethod())
            );

            return true;
        }
        // do a search for the value and the field:
        $transactions = $line['transactions'] ?? [];
        $field        = $this->configuration->getUniqueColumnType();
        $field        = 'external-id' === $field ? 'external_id' : $field;
        $value        = '';
        foreach ($transactions as $transactionIndex => $transaction) {
            $value = (string) ($transaction[$field] ?? '');
            if ('' === $value) {
                Log::debug(
                    sprintf(
                        'Identifier-based duplicate detection found no value ("") for field "%s" in transaction #%d (index #%d).', $field, $index,
                        $transactionIndex
                    )
                );
                continue;
            }
            $searchResult = $this->searchField($field, $value);
            if (0 !== $searchResult) {
                Log::debug(sprintf('Looks like field "%s" with value "%s" is not unique, found in group #%d. Return false', $field, $value, $searchResult));
                $message = sprintf('There is already a transaction with %s "%s" (<a href="%s/transactions/show/%d">link</a>).', $field, $value, $this->vanityURL, $searchResult);
                $this->addError($index, $message);

                return false;
            }
        }
        Log::debug(sprintf('Looks like field "%s" with value "%s" is unique, return false.', $field, $value));

        return true;
    }

    /**
     * Do a search at Firefly III and return the ID of the group found.
     *
     * @param string $field
     * @param string $value
     *
     * @return int
     */
    private function searchField(string $field, string $value): int
    {
        // search for the exact description and not just a part of it:
        $searchModifier = config(sprintf('csv_importer.search_modifier.%s', $field));
        $query          = sprintf('%s:"%s"', $searchModifier, $value);

        Log::debug(sprintf('Going to search for %s:%s using query %s', $field, $value, $query));

        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new GetSearchTransactionsRequest($url, $token);
        $request->setQuery($query);
        try {
            /** @var GetTransactionsResponse $response */
            $response = $request->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());

            return 0;
        }
        if (0 === $response->count()) {
            return 0;
        }
        $first = $response->current();
        Log::debug(sprintf('Found %d transaction(s). Return group ID #%d.', $response->count(), $first->id));

        return $first->id;
    }

    /**
     * @param int   $index
     * @param array $line
     *
     * @return array
     */
    private function processTransaction(int $index, array $line): array
    {
        $return  = [];
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new PostTransactionRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        Log::debug('Submitting to Firefly III:', $line);
        $request->setBody($line);
        try {
            $response = $request->post();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $message = sprintf('Submission HTTP error: %s', $e->getMessage());
            $this->addError($index, $message);

            return $return;
        }

        if ($response instanceof ValidationErrorResponse) {
            foreach ($response->errors->messages() as $key => $errors) {
                Log::error(sprintf('Submission error: %d', $key), $errors);
                foreach ($errors as $error) {
                    $msg = sprintf('%s: %s (original value: "%s")', $key, $error, $this->getOriginalValue($key, $line));
                    // plus 1 to keep the count.
                    $this->addError($index, $msg);
                    Log::error($msg);
                }
            }

            return $return;
        }

        if ($response instanceof PostTransactionResponse) {
            /** @var TransactionGroup $group */
            $group = $response->getTransactionGroup();
            if (null === $group) {
                $message = 'Could not create transaction. Unexpected empty response from Firefly III. Check the logs.';
                Log::error($message, $response->getRawData());
                $this->addError($index, $message);

                return $return;
            }
            $return = [
                'group_id' => $group->id,
                'journals' => [],
            ];
            foreach ($group->transactions as $transaction) {
                $message = sprintf(
                    'Created %s <a target="_blank" href="%s">#%d "%s"</a> (%s %s)',
                    $transaction->type,
                    sprintf('%s/transactions/show/%d', $this->vanityURL, $group->id),
                    $group->id,
                    e($transaction->description),
                    $transaction->currencyCode,
                    round((float) $transaction->amount, (int) $transaction->currencyDecimalPlaces)
                );
                // plus 1 to keep the count.
                $this->addMessage($index, $message);
                $this->compareArrays($index, $line, $group);
                Log::info($message);
                $return['journals'][$transaction->id] = $transaction->tags;
            }
        }

        return $return;
    }

    /**
     * @param string $key
     * @param array  $transaction
     *
     * @return string
     */
    private function getOriginalValue(string $key, array $transaction): string
    {
        $parts = explode('.', $key);
        if (1 === count($parts)) {
            return $transaction[$key] ?? '(not found)';
        }
        if (3 !== count($parts)) {
            return '(unknown)';
        }
        $index = (int) $parts[1];

        return (string) ($transaction['transactions'][$index][$parts[2]] ?? '(not found)');
    }

    /**
     * @param int              $lineIndex
     * @param array            $line
     * @param TransactionGroup $group
     */
    private function compareArrays(int $lineIndex, array $line, TransactionGroup $group): void
    {
        // some fields may not have survived. Be sure to warn the user about this.
        /** @var Transaction $transaction */
        foreach ($group->transactions as $index => $transaction) {
            // compare currency ID
            if (null !== $line['transactions'][$index]['currency_id']
                && (int) $line['transactions'][$index]['currency_id'] !== (int) $transaction->currencyId
            ) {
                $this->addWarning(
                    $lineIndex,
                    sprintf(
                        'Line #%d may have had its currency changed (from ID #%d to ID #%d). This happens because the associated asset account overrules the currency of the transaction.',
                        $lineIndex, $line['transactions'][$index]['currency_id'], (int) $transaction->currencyId
                    )
                );
            }
            // compare currency code:
            if (null !== $line['transactions'][$index]['currency_code']
                && $line['transactions'][$index]['currency_code'] !== $transaction->currencyCode
            ) {
                $this->addWarning(
                    $lineIndex,
                    sprintf(
                        'Line #%d may have had its currency changed (from "%s" to "%s"). This happens because the associated asset account overrules the currency of the transaction.',
                        $lineIndex, $line['transactions'][$index]['currency_code'], $transaction->currencyCode
                    )
                );
            }

        }
    }

    /**
     * @param array $groupInfo
     */
    private function addTagToGroups(array $groupInfo): void
    {
        if ([] === $groupInfo) {
            Log::info('No info on group.');

            return;
        }
        if (false === $this->addTag) {
            Log::debug('Will not add import tag.');

            return;
        }

        $groupId = (int) $groupInfo['group_id'];
        Log::debug(sprintf('Going to add import tag to transaction group #%d', $groupId));
        $body = [
            'transactions' => [],
        ];
        /**
         * @var int   $journalId
         * @var array $currentTags
         */
        foreach ($groupInfo['journals'] as $journalId => $currentTags) {
            $currentTags[]          = $this->tag;
            $body['transactions'][] =
                [
                    'transaction_journal_id' => $journalId,
                    'tags'                   => $currentTags,
                ];
        }
        $url     = Token::getURL();
        $token   = Token::getAccessToken();
        $request = new PutTransactionRequest($url, $token, $groupId);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setBody($body);
        try {
            $request->put();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            $this->addError(0, 'Could not store transaction: see the log files.');
        }
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->setAddTag($configuration->isAddImportTag());
    }

    /**
     * @param bool $addTag
     */
    public function setAddTag(bool $addTag): void
    {
        $this->addTag = $addTag;
    }


}
