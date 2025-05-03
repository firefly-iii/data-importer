<?php

/*
 * ApiSubmitter.php
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

namespace App\Services\Shared\Import\Routine;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Submission\ProgressInformation;
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
use Illuminate\Support\Facades\Log;

/**
 * Class ApiSubmitter
 */
class ApiSubmitter
{
    use ProgressInformation;

    private array         $accountInfo;
    private bool          $addTag;
    private Configuration $configuration;
    private bool          $createdTag;
    private array         $mapping;
    private string        $tag;
    private string        $tagDate;
    private string        $vanityURL;

    /**
     * @throws ImporterErrorException
     */
    public function processTransactions(array $lines): void
    {
        $this->createdTag = false;
        $this->tag        = $this->parseTag();
        $this->tagDate    = date('Y-m-d');
        $count            = count($lines);
        $uniqueCount      = 0;
        app('log')->info(sprintf('Going to submit %d transactions to your Firefly III instance.', $count));


        $this->vanityURL  = SecretManager::getVanityURL();

        app('log')->debug(sprintf('Vanity URL : "%s"', $this->vanityURL));

        /**
         * @var int   $index
         * @var array $line
         */
        foreach ($lines as $index => $line) {
            app('log')->debug(sprintf('Now submitting transaction %d/%d', $index + 1, $count));
            // first do local duplicate transaction check (the "cell" method):
            $unique    = $this->uniqueTransaction($index, $line);
            if (null === $unique) {
                app('log')->debug(sprintf('Transaction #%d is not checked beforehand on uniqueness.', $index + 1));
                ++$uniqueCount;
            }
            if (true === $unique) {
                app('log')->debug(sprintf('Transaction #%d is unique.', $index + 1));
                ++$uniqueCount;
            }
            if (false === $unique) {
                app('log')->debug(sprintf('Transaction #%d is NOT unique.', $index + 1));

                continue;
            }
            $groupInfo = $this->processTransaction($index, $line);
            $this->addTagToGroups($groupInfo);
        }

        app('log')->info(sprintf('Done submitting %d transactions to your Firefly III instance.', $count));
        app('log')->info(sprintf('Actually imported and not duplicate: %d.', $uniqueCount));
    }

    private function parseTag(): string
    {
        // $this->tag        = sprintf('Data Import on %s', date('Y-m-d \@ H:i'));
        $customTag = $this->configuration->getCustomTag();
        if ('' === $customTag) {
            // return default tag:
            return sprintf('Data Import on %s', date('Y-m-d \@ H:i'));
        }
        $items     = [
            '%year%'        => date('Y'),
            '%month%'       => date('m'),
            '%month_full%'  => date('F'),
            '%day%'         => date('d'),
            '%day_of_week%' => date('l'),
            '%hour%'        => date('H'),
            '%minute%'      => date('i'),
            '%second%'      => date('s'),
            '%date%'        => date('Y-m-d'),
            '%time%'        => date('H:i'),
            '%datetime%'    => date('Y-m-d \@ H:i'),
            '%version%'     => config('importer.version'),
        ];
        $result    = str_replace(array_keys($items), array_values($items), $customTag);
        app('log')->debug(sprintf('Custom tag is "%s", parsed into "%s"', $customTag, $result));

        return $result;
    }

    /**
     * Verify if the transaction is unique, based on the configuration
     * and the content of the transaction. Returns a boolean.
     */
    private function uniqueTransaction(int $index, array $line): ?bool
    {
        if ('cell' !== $this->configuration->getDuplicateDetectionMethod()) {
            app('log')->debug(
                sprintf('Duplicate detection method is "%s", so this method is skipped (return true).', $this->configuration->getDuplicateDetectionMethod())
            );

            return null;
        }
        // do a search for the value and the field:
        $transactions = $line['transactions'] ?? [];
        $field        = $this->configuration->getUniqueColumnType();
        $field        = 'external-id' === $field ? 'external_id' : $field;
        $field        = 'note' === $field ? 'notes' : $field;
        $value        = '';
        foreach ($transactions as $transactionIndex => $transaction) {
            $value        = (string) ($transaction[$field] ?? '');
            if ('' === $value) {
                app('log')->debug(
                    sprintf(
                        'Identifier-based duplicate detection found no value ("") for field "%s" in transaction #%d (index #%d).',
                        $field,
                        $index,
                        $transactionIndex
                    )
                );

                continue;
            }
            $searchResult = $this->searchField($field, $value);
            if (0 !== $searchResult) {
                app('log')->debug(
                    sprintf('Looks like field "%s" with value "%s" is not unique, found in group #%d. Return false', $field, $value, $searchResult)
                );
                $message = sprintf(
                    '[a115]: There is already a transaction with %s "%s" (<a href="%s/transactions/show/%d">link</a>).',
                    $field,
                    $value,
                    $this->vanityURL,
                    $searchResult
                );
                if (false === config('importer.ignore_duplicate_errors')) {
                    $this->addError($index, $message);
                }

                return false;
            }
        }
        app('log')->debug(sprintf('Looks like field "%s" with value "%s" is unique, return false.', $field, $value));

        return true;
    }

    /**
     * Do a search at Firefly III and return the ID of the group found.
     */
    private function searchField(string $field, string $value): int
    {
        // search for the exact description and not just a part of it:
        $searchModifier = config(sprintf('csv.search_modifier.%s', $field));
        $query          = sprintf('%s:"%s"', $searchModifier, $value);

        app('log')->debug(sprintf('Going to search for %s:%s using query %s', $field, $value, $query));

        $url            = SecretManager::getBaseUrl();
        $token          = SecretManager::getAccessToken();
        $request        = new GetSearchTransactionsRequest($url, $token);
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setVerify(config('importer.connection.verify'));
        $request->setQuery($query);

        try {
            /** @var GetTransactionsResponse $response */
            $response = $request->get();
        } catch (ApiHttpException $e) {
            app('log')->error($e->getMessage());

            return 0;
        }
        if (0 === $response->count()) {
            return 0;
        }
        $first          = $response->current();
        app('log')->debug(sprintf('Found %d transaction(s). Return group ID #%d.', $response->count(), $first->id));

        return $first->id;
    }

    private function processTransaction(int $index, array $line): array
    {
        ++$index;
        $line    = $this->cleanupLine($line);
        $return  = [];
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new PostTransactionRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        app('log')->debug(sprintf('Submitting to Firefly III: %s', json_encode($line)));
        $request->setBody($line);

        try {
            $response = $request->post();
        } catch (ApiHttpException $e) {
            $isDeleted = false;
            $body      = $request->getResponseBody();
            $json      = json_decode($body, true);
            // before we complain, first check what the error is:
            if (is_array($json) && array_key_exists('message', $json)) {
                if (str_contains($json['message'], '200032')) {
                    $isDeleted = true;
                }
            }
            if (true === $isDeleted && false === config('importer.ignore_not_found_transactions')) {
                $this->addWarning($index, 'The transaction was created, but deleted by a rule.');
                app('log')->error($e->getMessage());

                return $return;
            }
            if (true === $isDeleted && true === config('importer.ignore_not_found_transactions')) {
                Log::info('The transaction was deleted by a rule, but this is ignored by the importer.');

                return $return;
            }
            $message   = sprintf('[a116]: Submission HTTP error: %s', e($e->getMessage()));
            app('log')->error($e->getMessage());
            $this->addError($index, $message);

            return $return;
        }

        if ($response instanceof ValidationErrorResponse) {
            foreach ($response->errors->messages() as $key => $errors) {
                app('log')->error(sprintf('Submission error: %d', $key), $errors);
                foreach ($errors as $error) {
                    $msg = sprintf('[a117]: %s: %s (original value: "%s")', $key, $error, $this->getOriginalValue($key, $line));
                    if (false === $this->isDuplicationError($key, $error) || false === config('importer.ignore_duplicate_errors')) {
                        $this->addError($index, $msg);
                    }
                    app('log')->error($msg);
                }
            }

            return $return;
        }

        if ($response instanceof PostTransactionResponse) {
            /** @var TransactionGroup $group */
            $group  = $response->getTransactionGroup();
            if (null === $group) {
                $message = '[a118]: Could not create transaction. Unexpected empty response from Firefly III. Check the logs.';
                app('log')->error($message, $response->getRawData());
                $this->addError($index, $message);

                return $return;
            }

            // perhaps zero transactions in the array.
            if (0 === count($group->transactions)) {
                $message = '[a119]: Could not create transaction. Transaction-count from Firefly III is zero. Check the logs.';
                app('log')->error($message, $response->getRawData());
                $this->addError($index, $message);

                return $return;
            }

            $return = [
                'group_id' => $group->id,
                'journals' => [],
            ];
            foreach ($group->transactions as $transaction) {
                $message                              = sprintf(
                    'Created %s <a target="_blank" href="%s">#%d "%s"</a> (%s %s)',
                    $transaction->type,
                    sprintf('%s/transactions/show/%d', $this->vanityURL, $group->id),
                    $group->id,
                    e($transaction->description),
                    $transaction->currencyCode,
                    round((float) $transaction->amount, (int) $transaction->currencyDecimalPlaces) // float but only for display purposes
                );
                // plus 1 to keep the count.
                $this->addMessage($index, $message);
                $this->compareArrays($index, $line, $group);
                app('log')->info($message);
                $return['journals'][$transaction->id] = $transaction->tags;
            }
        }

        return $return;
    }

    private function cleanupLine(array $line): array
    {
        app('log')->debug('Going to map data for this line.');
        if (array_key_exists(0, $this->mapping)) {
            app('log')->debug('Configuration has mapping for opposing account name!');

            /**
             * @var int   $index
             * @var array $transaction
             */
            foreach ($line['transactions'] as $index => $transaction) {
                if ('withdrawal' === $transaction['type']) {
                    // replace destination_name with destination_id
                    $destination = $transaction['destination_name'] ?? '';
                    if (array_key_exists($destination, $this->mapping[0])) {
                        unset($transaction['destination_name'], $transaction['destination_iban']);

                        $transaction['destination_id'] = $this->mapping[0][$destination];
                        app('log')->debug(
                            sprintf('Replaced destination name "%s" with a reference to account id #%d', $destination, $this->mapping[0][$destination])
                        );
                    }
                }
                if ('deposit' === $transaction['type']) {
                    // replace source_name with source_id
                    $source = $transaction['source_name'] ?? '';
                    if (array_key_exists($source, $this->mapping[0])) {
                        unset($transaction['source_name'], $transaction['source_iban']);

                        $transaction['source_id'] = $this->mapping[0][$source];
                        app('log')->debug(sprintf('Replaced source name "%s" with a reference to account id #%d', $source, $this->mapping[0][$source]));
                    }
                }
                if ('' === trim((string) $transaction['description'] ?? '')) {
                    $transaction['description'] = '(no description)';
                }
                $line['transactions'][$index] = $this->updateTransactionType($transaction);
            }
        }

        return $line;
    }

    private function updateTransactionType(array $transaction): array
    {
        if (array_key_exists('source_id', $transaction) && array_key_exists('destination_id', $transaction)) {
            app('log')->debug('Transaction has source_id/destination_id');
            $sourceId        = (int) $transaction['source_id'];
            $destinationId   = (int) $transaction['destination_id'];
            $sourceType      = $this->accountInfo[$sourceId] ?? 'unknown';
            $destinationType = $this->accountInfo[$destinationId] ?? 'unknown';
            $combi           = sprintf('%s-%s', $sourceType, $destinationType);
            app('log')->debug(sprintf('Account type combination is "%s"', $combi));
            if ('asset-asset' === $combi) {
                app('log')->debug('Both accounts are assets, so this transaction is a transfer.');
                $transaction['type'] = 'transfer';
            }
        }

        return $transaction;
    }

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

    private function isDuplicationError(string $key, string $error): bool
    {
        if ('transactions.0.description' === $key && str_contains($error, 'Duplicate of transaction #')) {
            app('log')->debug('This is a duplicate transaction error');

            return true;
        }
        app('log')->debug('This is not a duplicate transaction error');

        return false;
    }

    private function compareArrays(int $lineIndex, array $line, TransactionGroup $group): void
    {
        // some fields may not have survived. Be sure to warn the user about this.
        /** @var Transaction $transaction */
        foreach ($group->transactions as $index => $transaction) {
            // compare currency ID
            if (array_key_exists('currency_id', $line['transactions'][$index]) && null !== $line['transactions'][$index]['currency_id']
                && (int) $line['transactions'][$index]['currency_id'] !== (int) $transaction->currencyId
            ) {
                $this->addWarning(
                    $lineIndex,
                    sprintf(
                        'Line #%d may have had its currency changed (from ID #%d to ID #%d). This happens because the associated asset account overrules the currency of the transaction.',
                        $lineIndex,
                        $line['transactions'][$index]['currency_id'],
                        (int) $transaction->currencyId
                    )
                );
            }
            // compare currency code:
            if (array_key_exists('currency_code', $line['transactions'][$index]) && null !== $line['transactions'][$index]['currency_code']
                && $line['transactions'][$index]['currency_code'] !== $transaction->currencyCode
            ) {
                $this->addWarning(
                    $lineIndex,
                    sprintf(
                        'Line #%d may have had its currency changed (from "%s" to "%s"). This happens because the associated asset account overrules the currency of the transaction.',
                        $lineIndex,
                        $line['transactions'][$index]['currency_code'],
                        $transaction->currencyCode
                    )
                );
            }
        }
    }

    private function addTagToGroups(array $groupInfo): void
    {
        if ([] === $groupInfo) {
            app('log')->debug('Group is empty, may not have been stored correctly.');

            return;
        }
        if (false === $this->addTag) {
            app('log')->debug('Will not add import tag.');

            return;
        }
        if (false === $this->createdTag) {
            $this->createTag();
            $this->createdTag = true;
        }

        $groupId = (int) $groupInfo['group_id'];
        app('log')->debug(sprintf('Going to add import tag to transaction group #%d', $groupId));
        $body    = [
            'transactions' => [],
        ];

        /**
         * @var int   $journalId
         * @var array $currentTags
         */
        foreach ($groupInfo['journals'] as $journalId => $currentTags) {
            $currentTags[]          = $this->tag;
            $body['transactions'][] = [
                'transaction_journal_id' => $journalId,
                'tags'                   => $currentTags,
            ];
        }
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new PutTransactionRequest($url, $token, $groupId);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setBody($body);

        try {
            $request->put();
        } catch (ApiHttpException $e) {
            app('log')->error($e->getMessage());
            //            app('log')->error($e->getTraceAsString());
            $this->addError(0, '[a120]: Could not store transaction: see the log files.');
        }
        app('log')->debug(sprintf('Added import tag to transaction group #%d', $groupId));
    }

    private function createTag(): void
    {
        if (false === $this->addTag) {
            app('log')->debug('Not instructed to add a tag, so will not create one.');

            return;
        }
        $url     = SecretManager::getBaseUrl();
        $token   = SecretManager::getAccessToken();
        $request = new PostTagRequest($url, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $body    = [
            'tag'  => $this->tag,
            'date' => $this->tagDate,
        ];
        $request->setBody($body);

        try {
            /** @var PostTagResponse $response */
            $response = $request->post();
        } catch (ApiHttpException $e) {
            $message = sprintf('[a121]: Could not create tag. %s', $e->getMessage());
            app('log')->error($message);
            //            app('log')->error($e->getTraceAsString());
            $this->addError(0, $message);

            return;
        }
        if ($response instanceof ValidationErrorResponse) {
            app('log')->error(json_encode($response->errors->toArray()));

            return;
        }
        if (null !== $response->getTag()) {
            app('log')->info(sprintf('Created tag #%d "%s"', $response->getTag()->id, $response->getTag()->tag));
        }
    }

    public function setAccountInfo(array $accountInfo): void
    {
        $this->accountInfo = $accountInfo;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->setAddTag($configuration->isAddImportTag());
        $this->setMapping($configuration->getMapping());
    }

    public function setAddTag(bool $addTag): void
    {
        $this->addTag = $addTag;
    }

    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }
}
