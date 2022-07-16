<?php
/*
 * GenerateTransactions.php
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

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Transaction;
use App\Services\Nordigen\Request\GetAccountInformationRequest;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use Cache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountsRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountsResponse;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use ProgressInformation;

    private array         $accounts;
    private Configuration $configuration;
    private array         $targetAccounts;
    private array         $targetTypes;
    private array         $nordigenAccountInfo;

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts      = [];
        $this->targetTypes         = [];
        $this->nordigenAccountInfo = [];
        bcscale(16);
    }

    /**
     *
     */
    public function collectTargetAccounts(): void
    {
        if (config('importer.use_cache') && Cache::has('collect_target_accounts')) {
            app('log')->debug('Grab target accounts from cache.');
            $info                 = Cache::get('collect_target_accounts');
            $this->targetAccounts = $info['accounts'];
            $this->targetTypes    = $info['types'];

            return;
        }
        app('log')->debug('Going to collect all target accounts from Firefly III.');
        // send account list request to Firefly III.
        $token   = SecretManager::getAccessToken();
        $url     = SecretManager::getBaseUrl();
        $request = new GetAccountsRequest($url, $token);
        /** @var GetAccountsResponse $result */
        $result = $request->get();
        $return = [];
        $types  = [];
        /** @var Account $entry */
        foreach ($result as $entry) {
            $type = $entry->type;
            if (in_array($type, ['reconciliation', 'initial-balance', 'expense', 'revenue'], true)) {
                continue;
            }
            $iban = $this->filterSpaces((string)$entry->iban);

            if ('' !== $iban) {
                app('log')->debug(sprintf('Collected IBAN "%s" (%s) under ID #%d', $iban, $entry->type, $entry->id));
                $return[$iban] = $entry->id;
                $types[$iban]  = $entry->type;
            }
            $number = sprintf('%s.', (string)$entry->number);
            if ('.' !== $number) {
                app('log')->debug(sprintf('Collected account nr "%s" (%s) under ID #%d', $number, $entry->type, $entry->id));
                $return[$number] = $entry->id;
                $types[$number]  = $entry->type;
            }
        }
        $this->targetAccounts = $return;
        $this->targetTypes    = $types;
        app('log')->debug(sprintf('Collected %d accounts.', count($this->targetAccounts)), $this->targetAccounts);
        if (config('importer.use_cache')) {
            $array = [
                'accounts' => $return,
                'types'    => $types,
            ];
            Cache::put('collect_target_accounts', $array, 86400); // 24h
            app('log')->info('Stored collected accounts in cache.');
        }
    }

    /**
     * TODO the result of this method is currently not used.
     *
     * @throws ImporterErrorException
     */
    public function collectNordigenAccounts(): void
    {
        if (config('importer.use_cache') && Cache::has('collect_nordigen_accounts')) {
            app('log')->debug('Grab Nordigen accounts from cache.');
            $this->nordigenAccountInfo = Cache::get('collect_nordigen_accounts');

            return;
        }
        $url         = config('nordigen.url');
        $accessToken = TokenManager::getAccessToken();
        $info        = [];
        app('log')->debug('Going to collect account information from Nordigen.');
        /**
         * @var string $nordigenIdentifier
         * @var int    $account
         */
        foreach ($this->accounts as $nordigenIdentifier => $account) {
            app('log')->debug(sprintf('Now at #%d => %s', $account, $nordigenIdentifier));
            $set = [];
            // get account details
            $request = new GetAccountInformationRequest($url, $accessToken, $nordigenIdentifier);
            $request->setTimeOut(config('importer.connection.timeout'));
            /** @var ArrayResponse $response */
            try {
                $response = $request->get();
            } catch (ImporterHttpException $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
            $accountInfo               = $response->data['account'];
            $set['iban']               = $accountInfo['iban'] ?? '';
            $info[$nordigenIdentifier] = $set;
            app('log')->debug(sprintf('Collected IBAN "%s" for Nordigen account "%s"', $set['iban'], $nordigenIdentifier));
        }
        $this->nordigenAccountInfo = $info;
        if (config('importer.use_cache')) {
            Cache::put('collect_nordigen_accounts', $info, 86400); // 24h
            app('log')->info('Stored collected Nordigen accounts in cache.');
        }
    }

    /**
     * @param array $transactions
     *
     * @return array
     */
    public function getTransactions(array $transactions): array
    {
        app('log')->debug('Now generate transactions.');
        $return = [];
        /**
         * @var string $accountId
         * @var array  $entries
         */
        foreach ($transactions as $accountId => $entries) {
            $total = count($entries);
            app('log')->debug(sprintf('Going to parse account %s with %d transaction(s).', $accountId, $total));
            /**
             * @var int         $index
             * @var Transaction $entry
             */
            foreach ($entries as $index => $entry) {
                app('log')->debug(sprintf('[%d/%d] Parsing transaction (3)', ($index + 1), $total));
                $return[] = $this->generateTransaction($accountId, $entry);
                app('log')->debug(sprintf('[%d/%d] Done parsing transaction.', ($index + 1), $total));
            }
        }
        //$this->addMessage(0, sprintf('Parsed %d Nordigen transactions for further processing.', count($return)));
        app('log')->debug('Done parsing transactions.');

        return $return;
    }

    /**
     * TODO function is way too complex.
     *
     * @param string      $accountId
     * @param Transaction $entry
     *
     * @return array
     */
    private function generateTransaction(string $accountId, Transaction $entry): array
    {
        app('log')->debug(sprintf('Nordigen transaction: "%s" with amount %s %s', $entry->getDescription(), $entry->currencyCode, $entry->transactionAmount));

        $return      = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => $this->configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $valueDate   = $entry->getValueDate();
        $transaction = [
            'type'               => 'withdrawal',
            'date'               => $entry->getDate()->format('Y-m-d'),
            'datetime'           => $entry->getDate()->toW3cString(),
            'amount'             => $entry->transactionAmount,
            'description'        => $entry->getDescription(),
            'payment_date'       => is_null($valueDate) ? '' : $valueDate->format('Y-m-d'),
            'order'              => 0,
            'currency_code'      => $entry->currencyCode,
            'tags'               => [],
            'category_name'      => null,
            'category_id'        => null,
            'notes'              => $entry->getNotes(),
            'external_id'        => $entry->transactionId,
            'internal_reference' => $entry->accountIdentifier,
        ];

        if (1 === bccomp($entry->transactionAmount, '0')) {
            app('log')->debug('Amount is positive: assume transfer or deposit.');
            $transaction = $this->appendPositiveAmountInfo($accountId, $transaction, $entry);
        }

        if (-1 === bccomp($entry->transactionAmount, '0')) {
            app('log')->debug('Amount is negative: assume transfer or withdrawal.');
            $transaction = $this->appendNegativeAmountInfo($accountId, $transaction, $entry);
        }
        $return['transactions'][] = $transaction;
        app('log')->debug(sprintf('Parsed Nordigen transaction "%s".', $entry->transactionId), $transaction);


        return $return;
    }

    /**
     * @param string $name
     *
     * @return int|null
     */
    private function getMappedAccountId(string $name): ?int
    {
        if (isset($this->configuration->getMapping()['accounts'][$name])) {
            return (int)$this->configuration->getMapping()['accounts'][$name];
        }

        return null;
    }

    /**
     * @param int $mappedId
     *
     * @return string
     * @throws ImporterHttpException
     */
    private function getMappedAccountType(int $mappedId): string
    {
        if (!isset($this->configuration->getAccountTypes()[$mappedId])) {
            app('log')->warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            $accountType             = $this->getAccountType($mappedId);
            $accountTypes            = $this->configuration->getAccountTypes();
            $accountTypes[$mappedId] = $accountType;
            $this->configuration->setAccountTypes($accountTypes);

            app('log')->debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $accountType));

            return $accountType;
        }
        $type = $this->configuration->getAccountTypes()[$mappedId] ?? 'expense';
        app('log')->debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $type));

        return $type;
    }

    /**
     * @param int $accountId
     *
     * @return string
     * @throws ImporterHttpException
     */
    private function getAccountType(int $accountId): string
    {
        $token = SecretManager::getAccessToken();
        $url   = SecretManager::getBaseUrl();
        app('log')->debug(sprintf('Going to download account #%d', $accountId));
        $request = new GetAccountRequest($url, $token);
        $request->setId($accountId);
        /** @var GetAccountResponse $result */
        try {
            $result = $request->get();
        } catch (ApiHttpException $e) {
            throw new ImporterHttpException($e->getMessage(), 0, $e);
        }
        $type = $result->getAccount()->type;

        app('log')->debug(sprintf('Discovered that account #%d is of type "%s"', $accountId, $type));

        return $type;
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @return string
     * @throws ImporterErrorException
     */
    private function getTransactionType(string $source, string $destination): string
    {
        $combination = sprintf('%s-%s', $source, $destination);
        switch ($combination) {
            default:
                throw new ImporterErrorException(sprintf('Unknown combination: %s and %s', $source, $destination));
            case 'asset-liabilities':
            case 'asset-expense':
                return 'withdrawal';
            case 'asset-asset':
                return 'transfer';
            case 'liabilities-asset':
            case 'revenue-asset':
                return 'deposit';
        }
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
    }

    /**
     * Handle transaction information when the amount is positive, and this is probably a deposit or a transfer.
     *
     * @param string      $accountId
     * @param array       $transaction
     * @param Transaction $entry
     *
     * @return array
     * @throws ImporterHttpException
     */
    private function appendPositiveAmountInfo(string $accountId, array $transaction, Transaction $entry): array
    {
        // amount is positive: deposit or transfer. Nordigen account is the destination
        $transaction['type']   = 'deposit';
        $transaction['amount'] = $entry->transactionAmount;

        // destination is a Nordigen account (has to be!)
        $transaction['destination_id'] = (int)$this->accounts[$accountId];
        app('log')->debug(sprintf('Destination ID is now #%d, which should be a Firefly III asset account.', $transaction['destination_id']));

        // append source iban and number (if present)
        $transaction = $this->appendAccountFields($transaction, $entry, 'source');

        // TODO clean up mapping
        $mappedId = null;
        if (isset($transaction['source_name'])) {
            app('log')->debug(sprintf('Check if "%s" is mapped to an account by the user.', $transaction['source_name']));
            $mappedId = $this->getMappedAccountId($transaction['source_name']);
        }
        if (null === $mappedId) {
            app('log')->debug('Its not mapped by the user.');
        }

        if (null !== $mappedId && 0 !== $mappedId) {
            app('log')->debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $transaction['source_name'], $mappedId));
            $mappedType               = $this->getMappedAccountType($mappedId);
            $originalSourceName       = $transaction['source_name'];
            $transaction['source_id'] = $mappedId;
            // catch error here:
            try {
                $transaction['type'] = $this->getTransactionType($mappedType, 'asset');
                app('log')->debug(sprintf('Transaction type seems to be %s', $transaction['type']));
            } catch (ImporterErrorException $e) {
                app('log')->error($e->getMessage());
                app('log')->info('Will not use mapped ID, Firefly III account is of the wrong type.');
                unset($transaction['source_id']);
                $transaction['source_name'] = $originalSourceName;
            }
        }

        return $transaction;
    }

    /**
     * Handle transaction information when the amount is negative, and this is probably a withdrawal or a transfer.
     *
     * @param string      $accountId
     * @param array       $transaction
     * @param Transaction $entry
     *
     * @return array
     * @throws ImporterHttpException
     */
    private function appendNegativeAmountInfo(string $accountId, array $transaction, Transaction $entry): array
    {

        $transaction['amount']    = bcmul($entry->transactionAmount, '-1');
        $transaction['source_id'] = (int)$this->accounts[$accountId]; // TODO entry may not exist, then what?

        // append source iban and number (if present)
        $transaction = $this->appendAccountFields($transaction, $entry, 'destination');

        $mappedId = null;
        if (isset($transaction['destination_name'])) {
            app('log')->debug(sprintf('Check if "%s" is mapped to an account by the user.', $transaction['destination_name']));
            $mappedId = $this->getMappedAccountId($transaction['destination_name']);
        }
        if (null === $mappedId) {
            app('log')->debug('Its not mapped by the user.');
        }

        if (null !== $mappedId && 0 !== $mappedId) {
            app('log')->debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $transaction['destination_name'], $mappedId));
            $mappedType = $this->getMappedAccountType($mappedId);

            $originalDestName              = $transaction['destination_name'];
            $transaction['destination_id'] = $mappedId;
            // catch error here:
            try {
                $transaction['type'] = $this->getTransactionType('asset', $mappedType);
                app('log')->debug(sprintf('Transaction type seems to be %s', $transaction['type']));
            } catch (ImporterErrorException $e) {
                app('log')->error($e->getMessage());
                app('log')->info('Will not use mapped ID, Firefly III account is of the wrong type.');
                unset($transaction['destination_id']);
                $transaction['destination_name'] = $originalDestName;
            }
        }

        return $transaction;
    }

    /**
     * @param array       $transaction
     * @param Transaction $entry
     * @param string      $direction
     *
     * @return array
     */
    private function appendAccountFields(array $transaction, Transaction $entry, string $direction): array
    {
        app('log')->debug(sprintf('Now in %s(transaction, entry, "%s")', __METHOD__, $direction));
        switch ($direction) {
            default:
                die(sprintf('Cannot handle direction "%s"', $direction));
            case 'source':
                $iban      = $entry->getSourceIban();
                $number    = $entry->getSourceNumber();
                $name      = $entry->getSourceName();
                $idKey     = 'source_id';
                $ibanKey   = 'source_iban';
                $nameKey   = 'source_name';
                $numberKey = 'source_number';
                break;
            case 'destination':
                $iban      = $entry->getDestinationIban();
                $number    = $entry->getDestinationNumber();
                $name      = $entry->getDestinationName();
                $idKey     = 'destination_id';
                $ibanKey   = 'destination_iban';
                $nameKey   = 'destination_name';
                $numberKey = 'destination_number';
                break;
        }
        $numberSearch = sprintf('%s.', $number);

        // IBAN is also an ID, so use that!
        if ('' !== (string)$iban && array_key_exists((string)$iban, $this->targetAccounts)) {
            app('log')->debug(sprintf('Recognized %s (IBAN) as a Firefly III asset account so this is a transfer.', $iban));
            $transaction[$idKey] = $this->targetAccounts[$iban];
            $transaction['type'] = 'transfer';
        }

        // IBAN is not an ID, so just submit it as a field.
        if ('' === (string)$iban || !array_key_exists((string)$iban, $this->targetAccounts)) {
            app('log')->debug(sprintf('"%s" is not a valid IBAN OR not recognized as Firefly III asset account so submitted as-is.', $iban));
            // source is the other side:
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);
            if ('' !== (string)$iban) {
                app('log')->debug(sprintf('Set field "%s" to "%s".', $ibanKey, $iban));
                $transaction[$ibanKey] = $iban;
            }
            if ('' === (string)$iban) {
                app('log')->debug(sprintf('IBAN is "%s", so leave field "%s" empty.', $iban, $ibanKey));
            }
        }

        // source is also an ID, so use it!
        if ('' !== (string)$number && '.' !== $numberSearch && array_key_exists($numberSearch, $this->targetAccounts)) {
            app('log')->debug(sprintf('Recognized "%s" (number) as a Firefly III asset account so this is a transfer.', $number));
            $transaction[$idKey] = $this->targetAccounts[$numberSearch];
            $transaction['type'] = 'transfer';
        }

        if ('' === (string)$number || '.' === $numberSearch || !array_key_exists($numberSearch, $this->targetAccounts)) {
            app('log')->debug(sprintf('"%s" is not a valid account nr OR not recognized as Firefly III asset account so submitted as-is.', $number));
            // source is the other side:
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);
            if ('' !== (string)$number) {
                app('log')->debug(sprintf('Set field "%s" to "%s".', $numberKey, $number));
                $transaction[$numberKey] = $number;
            }
            if ('' === (string)$number) {
                app('log')->debug(sprintf('Account number is "%s", so leave field "%s" empty.', $number, $numberKey));
            }
        }
        app('log')->debug(sprintf('End of %s', __METHOD__));

        return $transaction;
    }

    /**
     * @param string $iban
     *
     * @return string
     */
    private function filterSpaces(string $iban): string
    {
        $search = [
            "\u{0001}", // start of heading
            "\u{0002}", // start of text
            "\u{0003}", // end of text
            "\u{0004}", // end of transmission
            "\u{0005}", // enquiry
            "\u{0006}", // ACK
            "\u{0007}", // BEL
            "\u{0008}", // backspace
            "\u{000E}", // shift out
            "\u{000F}", // shift in
            "\u{0010}", // data link escape
            "\u{0011}", // DC1
            "\u{0012}", // DC2
            "\u{0013}", // DC3
            "\u{0014}", // DC4
            "\u{0015}", // NAK
            "\u{0016}", // SYN
            "\u{0017}", // ETB
            "\u{0018}", // CAN
            "\u{0019}", // EM
            "\u{001A}", // SUB
            "\u{001B}", // escape
            "\u{001C}", // file separator
            "\u{001D}", // group separator
            "\u{001E}", // record separator
            "\u{001F}", // unit separator
            "\u{007F}", // DEL
            "\u{00A0}", // non-breaking space
            "\u{1680}", // ogham space mark
            "\u{180E}", // mongolian vowel separator
            "\u{2000}", // en quad
            "\u{2001}", // em quad
            "\u{2002}", // en space
            "\u{2003}", // em space
            "\u{2004}", // three-per-em space
            "\u{2005}", // four-per-em space
            "\u{2006}", // six-per-em space
            "\u{2007}", // figure space
            "\u{2008}", // punctuation space
            "\u{2009}", // thin space
            "\u{200A}", // hair space
            "\u{200B}", // zero width space
            "\u{202F}", // narrow no-break space
            "\u{3000}", // ideographic space
            "\u{FEFF}", // zero width no -break space
            "\x20", // plain old normal space
        ];

        return str_replace($search, '', $iban);
    }
}
