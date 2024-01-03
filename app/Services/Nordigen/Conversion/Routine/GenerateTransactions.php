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

use App\Exceptions\AgreementExpiredException;
use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\Model\Transaction;
use App\Services\Nordigen\Request\GetAccountInformationRequest;
use App\Services\Nordigen\Response\ArrayResponse;
use App\Services\Nordigen\TokenManager;
use App\Services\Shared\Authentication\SecretManager;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Support\Http\CollectsAccounts;
use App\Support\Internal\DuplicateSafetyCatch;
use Cache;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use CollectsAccounts;
    use DuplicateSafetyCatch;
    use ProgressInformation;

    private array         $accounts;
    private Configuration $configuration;
    private array         $nordigenAccountInfo;
    private array         $targetAccounts;
    private array         $targetTypes;
    public const string NUMBER_FORMAT = 'nr_%s';

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts      = [];
        $this->targetTypes         = [];
        $this->nordigenAccountInfo = [];
        bcscale(12);
    }

    /**
     * TODO the result of this method is currently not used.
     *
     * @throws AgreementExpiredException
     * @throws ImporterErrorException
     */
    public function collectNordigenAccounts(): void
    {
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
    }

    /**
     *
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        app('log')->debug('Nordigen: Defer account search to trait.');
        // defer to trait:
        $array = $this->collectAllTargetAccounts();
        foreach ($array as $number => $info) {
            $this->targetAccounts[$number] = $info['id'];
            $this->targetTypes[$number]    = $info['type'];
        }
        app('log')->debug(sprintf('Nordigen: Collected %d accounts.', count($this->targetAccounts)));
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
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
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
        app('log')->debug(sprintf('Now in %s($transaction, $entry, "%s")', __METHOD__, $direction));

        // these are the values we're going to use:
        switch ($direction) {
            default:
                die(sprintf('Cannot handle direction "%s"', $direction));
            case 'source':
                $iban      = $entry->getSourceIban();
                $number    = sprintf(self::NUMBER_FORMAT, $entry->getSourceNumber());
                $name      = $entry->getSourceName();
                $idKey     = 'source_id';
                $ibanKey   = 'source_iban';
                $nameKey   = 'source_name';
                $numberKey = 'source_number';
                break;
            case 'destination':
                $iban      = $entry->getDestinationIban();
                $number    = sprintf(self::NUMBER_FORMAT, $entry->getDestinationNumber());
                $name      = $entry->getDestinationName();
                $idKey     = 'destination_id';
                $ibanKey   = 'destination_iban';
                $nameKey   = 'destination_name';
                $numberKey = 'destination_number';
                break;
        }
        // temp measure to make sure it's a string:
        $iban = (string)$iban;
        app('log')->debug('Done collecting account numbers and names.');

        // The data importer determines the account type based on the IBAN.
        $accountType = $this->targetTypes[$iban] ?? 'unknown';

        // If the IBAN is a known target account, but it's not a liability, the data importer knows for sure this is a transfer.
        // it will save the ID and nothing else.
        if ('liabilities' !== $accountType
            && '' !== $iban
            && array_key_exists((string)$iban, $this->targetAccounts)) {
            app('log')->debug(sprintf('Recognized "%s" (IBAN) as a Firefly III asset account so this is a transfer.', $iban));
            app('log')->debug(sprintf('Type of "%s" (IBAN) is a "%s".', $iban, $this->targetTypes[$iban]));
            $transaction[$idKey] = $this->targetAccounts[$iban];
            $transaction['type'] = 'transfer';
        }

        // If the IBAN is not set in the transaction, or the IBAN is not in the array of asset accounts
        if ('' === $iban || !array_key_exists($iban, $this->targetAccounts)) {
            app('log')->debug(sprintf('"%s" is not a valid IBAN OR not recognized as Firefly III asset account so submitted as-is.', $iban));
            app('log')->debug(sprintf('IBAN is "%s", so leave field "%s" empty.', $iban, $ibanKey));
            // The data importer will set the name as it exists in the transaction:
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);

            app('log')->debug(sprintf('Field "%s" will  be set to "%s".', $nameKey, $transaction[$nameKey]));
        }

        // if the IBAN is set, the IBAN will be put into the array as well.
        if ('' !== $iban) {
            app('log')->debug(sprintf('Set field "%s" to "%s".', $ibanKey, $iban));
            $transaction[$ibanKey] = $iban;
        }
        // If the account number is a known target account, but it's not a liability, the data importer knows for sure this is a transfer.
        // it will save the ID and nothing else.
        $accountType = $this->targetTypes[$number] ?? 'unknown';
        if (
            'liabilities' !== $accountType
            && '' !== $number && sprintf(self::NUMBER_FORMAT, '') !== $number
            && array_key_exists($number, $this->targetAccounts)) {
            app('log')->debug(sprintf('Recognized "%s" (number) as a Firefly III asset account so this is a transfer.', $number));
            $transaction[$idKey] = $this->targetAccounts[$number];
            $transaction['type'] = 'transfer';
        }

        // if the account number is empty, then it's submitted as is:
        if ('' === $number || !array_key_exists($number, $this->targetAccounts)) {
            app('log')->debug(sprintf('"%s" is not a valid account number OR not recognized as Firefly III asset account so submitted as-is.', $number));
            app('log')->debug(sprintf('Account number is "%s", so leave field "%s" empty.', $number, $numberKey));
            // The data importer will set the name in the transaction
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);

            app('log')->debug(sprintf('Field "%s" will  be set to "%s".', $nameKey, $transaction[$nameKey]));
        }

        if ('' !== $number) {
            app('log')->debug(sprintf('Set field "%s" to "%s".', $numberKey, substr($number, 3)));
            $transaction[$numberKey] = substr($number, 3);
        }

        app('log')->debug(sprintf('End of %s', __METHOD__));

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

        $transaction = $this->negativeTransactionSafetyCatch($transaction, (string)$entry->getDestinationName(), (string)$entry->getDestinationIban());

        app('log')->debug(sprintf('source_id = %d, destination_id = "%s", destination_name = "%s", destination_iban = "%s"', $transaction['source_id'], $transaction['destination_id'] ?? '', $transaction['destination_name'] ?? '', $transaction['destination_iban'] ?? ''));

        return $transaction;
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

        $transaction = $this->positiveTransactionSafetyCatch($transaction, (string)$entry->getSourceName(), (string)$entry->getSourceIban());

        app('log')->debug(sprintf('destination_id = %d, source_name = "%s", source_iban = "%s", source_id = "%s"', $transaction['destination_id'] ?? '', $transaction['source_name'] ?? '', $transaction['source_iban'] ?? '', $transaction['source_id'] ?? ''));

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

    /**
     * TODO function is way too complex.
     *
     * @param string      $accountId
     * @param Transaction $entry
     *
     * @return array
     * @throws ImporterHttpException
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
            'type'                   => 'withdrawal',
            'date'                   => $entry->getDate()->toW3cString(),
            'datetime'               => $entry->getDate()->toW3cString(),
            'amount'                 => $entry->transactionAmount,
            'description'            => $entry->getDescription(),
            'payment_date'           => null === $valueDate ? '' : $valueDate->format('Y-m-d'),
            'order'                  => 0,
            'currency_code'          => $entry->currencyCode,
            'tags'                   => $entry->tags,
            'category_name'          => null,
            'category_id'            => null,
            'notes'                  => $entry->getNotes(),
            'external_id'            => $entry->getTransactionId(),
            'internal_reference'     => $entry->accountIdentifier,
            'additional-information' => $entry->additionalInformation,
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
        app('log')->debug(sprintf('Parsed Nordigen transaction "%s".', $entry->getTransactionId()), $transaction);


        return $return;
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
     * TODO Method "getAccountTypes" does not exist and I'm not sure what it is supposed to do.
     *
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
}
