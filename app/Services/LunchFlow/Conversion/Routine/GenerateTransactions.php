<?php

/*
 * GenerateTransactions.php
 * Copyright (c) 2025 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\LunchFlow\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Exceptions\ImporterHttpException;
use App\Models\ImportJob;
use App\Services\LunchFlow\Model\Transaction;
use App\Services\Shared\Authentication\SecretManager;
use App\Support\Http\CollectsAccounts;
use App\Support\Internal\DuplicateSafetyCatch;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use Illuminate\Support\Facades\Log;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use CollectsAccounts;
    use DuplicateSafetyCatch;

    public const string NUMBER_FORMAT = 'nr_%s';

    private array     $accounts;
    private array     $nordigenAccountInfo;
    private array     $targetAccounts;
    private array     $expenseAccounts;
    private array     $revenueAccounts;
    private array     $targetTypes;
    private array     $expenseAccountNames;
    private array     $revenueAccountNames;
    private ImportJob $importJob;

    private array $userAccounts; // contains ALL information on Firefly III asset accounts and liabilities.

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts      = [];
        $this->targetTypes         = [];
        $this->nordigenAccountInfo = [];
        $this->userAccounts        = [];
        $this->expenseAccounts     = [];
        $this->revenueAccounts     = [];
        $this->expenseAccountNames = [];
        $this->revenueAccountNames = [];
        $this->accounts            = [];
        bcscale(12);
    }

    /**
     * @throws ApiHttpException
     */
    public function collectTargetAccounts(): void
    {
        Log::debug('Lunch Flow: Defer account search to trait.');
        // defer to trait:
        $array = $this->collectAllTargetAccounts();
        foreach ($array as $number => $info) {
            $this->targetAccounts[$number] = $info['id'];
            $this->targetTypes[$number]    = $info['type'];
            $this->userAccounts[$number]   = $info;
        }

        // do the same for expense accounts.
        $array = $this->collectExpenseAccounts();
        foreach ($array as $number => $info) {
            $this->expenseAccounts[$number]     = $info['id'];
            $this->expenseAccountNames[$number] = $info['name'];
            $this->targetTypes[$number]         = $info['type'];
        }
        // do the same for revenue accounts
        $array = $this->collectRevenueAccounts();
        foreach ($array as $number => $info) {
            $this->revenueAccounts[$number]     = $info['id'];
            $this->revenueAccountNames[$number] = $info['name'];
            $this->targetTypes[$number]         = $info['type'];
        }

        Log::debug(sprintf('Lunch Flow: Collected %d target accounts.', count($this->targetAccounts)));
        Log::debug(sprintf('Lunch Flow: Collected %d expense accounts.', count($this->expenseAccounts)));
        Log::debug(sprintf('Lunch Flow: Collected %d revenue accounts.', count($this->revenueAccounts)));
    }

    public function getTransactions(array $transactions): array
    {
        Log::debug('Now generate transactions.');
        $return = [];

        /**
         * @var int   $accountId
         * @var array $entries
         */
        foreach ($transactions as $accountId => $entries) {
            $total = count($entries);
            Log::debug(sprintf('Going to parse account %s with %d transaction(s).', $accountId, $total));

            /**
             * @var int         $index
             * @var Transaction $entry
             */
            foreach ($entries as $index => $entry) {
                Log::debug(sprintf('[%s] [%d/%d] Parsing transaction (3)', config('importer.version'), $index + 1, $total));
                $return[] = $this->generateTransaction($accountId, $entry);
                Log::debug(sprintf('[%s] [%d/%d] Done parsing transaction.', config('importer.version'), $index + 1, $total));
            }
        }
        // $this->addMessage(0, sprintf('Parsed %d Nordigen transactions for further processing.', count($return)));
        Log::debug('Done parsing transactions.');

        return $return;
    }

    /**
     * FIXME function is way too complex.
     *
     * @throws ImporterHttpException
     */
    private function generateTransaction(int $accountId, Transaction $entry): array
    {
        $configuration            = $this->importJob->getConfiguration();
        Log::debug(sprintf('Lunch Flow transaction: "%s" with amount %s %s', $entry->getDescription(), $entry->currency, $entry->amount));

        $return                   = [
            'apply_rules'             => $configuration->isRules(),
            'error_if_duplicate_hash' => $configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];
        $transaction              = [
            'type'          => 'withdrawal',
            'date'          => $entry->getDate()->toW3cString(),
            'datetime'      => $entry->getDate()->toW3cString(),
            'amount'        => $entry->amount,
            'description'   => $entry->getDescription(),
            'order'         => 0,
            'currency_code' => $entry->currency,
            'category_name' => null,
            'category_id'   => null,
            'external_id'   => $entry->getTransactionId(),
            'bonus_tags'    => [],
        ];

        if (1 === bccomp($entry->amount, '0')) {
            Log::debug('Amount is positive: assume transfer or deposit.');
            $transaction = $this->appendPositiveAmountInfo($accountId, $transaction, $entry);
        }

        if (-1 === bccomp($entry->amount, '0')) {
            Log::debug('Amount is negative: assume transfer or withdrawal.');
            $transaction = $this->appendNegativeAmountInfo($accountId, $transaction, $entry);
        }

        $return['transactions'][] = $transaction;
        Log::debug(sprintf('[%s] Parsed Lunch Flow transaction "%s".', config('importer.version'), $entry->getTransactionId()), $transaction);

        return $return;
    }

    /**
     * Handle transaction information when the amount is positive, and this is probably a deposit or a transfer.
     *
     * @throws ImporterHttpException
     */
    private function appendPositiveAmountInfo(int $accountId, array $transaction, Transaction $entry): array
    {
        // amount is positive: deposit or transfer. Lunch Flow account is the destination
        $transaction['type']           = 'deposit';
        $transaction['amount']         = $entry->amount;

        // destination is a Lunch Flow account (has to be!)
        $transaction['destination_id'] = (int)$this->accounts[$accountId];
        Log::debug(sprintf('Destination ID is now #%d, which should be a Firefly III asset account.', $transaction['destination_id']));

        // append source iban and number (if present)
        $transaction                   = $this->appendAccountFields($transaction, $entry, 'source');

        // FIXME clean up mapping
        $mappedId                      = null;
        if (isset($transaction['source_name'])) {
            Log::debug(sprintf('Check if "%s" is mapped to an account by the user.', $transaction['source_name']));
            $mappedId = $this->getMappedAccountId($transaction['source_name']);
        }
        if (null === $mappedId) {
            Log::debug('Its not mapped by the user.');
        }

        if (null !== $mappedId && 0 !== $mappedId) {
            Log::debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $transaction['source_name'], $mappedId));
            $mappedType               = $this->getMappedAccountType($mappedId);
            $originalSourceName       = $transaction['source_name'];
            $transaction['source_id'] = $mappedId;

            // catch error here:
            try {
                $transaction['type'] = $this->getTransactionType($mappedType, 'asset');
                Log::debug(sprintf('Transaction type seems to be %s', $transaction['type']));
            } catch (ImporterErrorException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                Log::info('Will not use mapped ID, Firefly III account is of the wrong type.');
                unset($transaction['source_id']);
                $transaction['source_name'] = $originalSourceName;
            }
        }

        $transaction                   = $this->positiveTransactionSafetyCatch($transaction, '', '');

        Log::debug(sprintf('destination_id = %d, source_name = "%s", source_iban = "%s", source_id = "%s"', $transaction['destination_id'] ?? '', $transaction['source_name'] ?? '', $transaction['source_iban'] ?? '', $transaction['source_id'] ?? ''));

        return $transaction;
    }

    private function appendAccountFields(array $transaction, Transaction $entry, string $direction): array
    {
        Log::debug(sprintf('Now in %s($transaction, $entry, "%s")', __METHOD__, $direction));

        // these are the values we're going to use:
        switch ($direction) {
            default:
                exit(sprintf('Cannot handle direction "%s"', $direction));

            case 'source':
                $iban      = '';
                $number    = sprintf(self::NUMBER_FORMAT, '');
                $name      = '';
                $idKey     = 'source_id';
                $ibanKey   = 'source_iban';
                $nameKey   = 'source_name';
                $numberKey = 'source_number';

                break;

            case 'destination':
                $iban      = '';
                $number    = sprintf(self::NUMBER_FORMAT, '');
                $name      = $entry->getDestinationName();
                $idKey     = 'destination_id';
                $ibanKey   = 'destination_iban';
                $nameKey   = 'destination_name';
                $numberKey = 'destination_number';

                break;
        }
        // temp measure to make sure it's a string:
        Log::debug('Done collecting account numbers and names.');

        if ('' !== $number) {
            Log::debug(sprintf('Set field "%s" to "%s".', $numberKey, substr($number, 3)));
            $transaction[$numberKey] = substr($number, 3);
        }

        // The data importer determines the account type based on the IBAN.
        $accountType = (string)($this->targetTypes[$iban] ?? 'unknown');

        // If the IBAN is a known target account, but it's not a liability OR revenue OR expense, the data importer knows for sure this is a transfer.
        // it will save the ID and nothing else.
        if ($this->isAssetAccount($accountType, $iban)) {
            Log::debug(sprintf('Recognized "%s" (IBAN) as a Firefly III asset account so this is a transfer.', $iban));
            Log::debug(sprintf('Type of "%s" (IBAN) is a "%s".', $iban, $this->targetTypes[$iban]));
            $transaction[$idKey] = $this->targetAccounts[$iban];
            $transaction['type'] = 'transfer';
        }

        // if the account type (based on IBAN only!) is a revenue or an expense, submit the name as an extra tag.
        if ($this->isExpenseOrRevenue($accountType, $iban)) {
            Log::debug(sprintf('Recognized "%s" (IBAN) as a Firefly III %s account.', $iban, $accountType));
            $bonusTag    = $name ?? sprintf('(unknown %s account)', $direction);
            $accountName = $this->getRevenueOrExpenseName($iban, $accountType);
            if ($bonusTag !== $accountName) {
                Log::debug(sprintf('Add account name "%s" as extra tag because the recognized account is called "%s".', $bonusTag, $accountName));
                $transaction['notes'] = sprintf("%s\n\nOriginal account name: %s", $transaction['notes'], $bonusTag);
            }
        }

        // If the IBAN is not set in the transaction, or the IBAN is not in the array of asset accounts
        if ($this->ibanIsEmpty($iban) || !array_key_exists($iban, $this->targetAccounts)) {
            Log::debug(sprintf('"%s" is not a valid IBAN OR not recognized as Firefly III asset account, submit the name only.', $iban));
            Log::debug(sprintf('IBAN is "%s", so leave field "%s" empty.', $iban, $ibanKey));
            // The data importer will set the name as it exists in the transaction:
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);
            Log::debug(sprintf('Field "%s" will  be set to "%s".', $nameKey, $transaction[$nameKey]));
        }


        // If the account number is a known target account, but it's not a liability, the data importer knows for sure this is a transfer.
        // it will save the ID and nothing else.
        $accountType = (string)($this->targetTypes[$number] ?? 'unknown');
        if ($this->isAssetAccount($accountType, $number) && sprintf(self::NUMBER_FORMAT, '') !== $number) {
            Log::debug(sprintf('Recognized "%s" (number) as a Firefly III asset account so this is a transfer.', $number));
            $transaction[$idKey] = $this->targetAccounts[$number];
            $transaction['type'] = 'transfer';
        }

        // if the account number is empty, then it's submitted as is:
        if ($this->ibanIsEmpty($number) || !array_key_exists($number, $this->targetAccounts)) {
            Log::debug(sprintf('"%s" is not a valid account number OR not recognized as Firefly III asset account so submitted as-is.', $number));
            Log::debug(sprintf('Account number is "%s", so leave field "%s" empty.', $number, $numberKey));
            // The data importer will set the name in the transaction
            $transaction[$nameKey] = $name ?? sprintf('(unknown %s account)', $direction);

            Log::debug(sprintf('Field "%s" will  be set to "%s".', $nameKey, $transaction[$nameKey]));
        }


        Log::debug(sprintf('End of %s', __METHOD__));

        return $transaction;
    }

    private function getMappedAccountId(string $name): ?int
    {
        $configuration = $this->importJob->getConfiguration();
        if (isset($configuration->getMapping()['accounts'][$name])) {
            return (int)$configuration->getMapping()['accounts'][$name];
        }

        return null;
    }

    /**
     * FIXME Method "getAccountTypes" does not exist and I'm not sure what it is supposed to do.
     *
     * @throws ImporterHttpException
     */
    private function getMappedAccountType(int $mappedId): string
    {
        throw new ImporterErrorException('Please open an issue when you run into this. Share many details. Thanks!');
        $configuration = $this->importJob->getConfiguration();

        if (!isset($configuration->getAccountTypes()[$mappedId])) {
            Log::warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            $accountType             = $this->getAccountType($mappedId);
            $accountTypes            = $configuration->getAccountTypes();
            $accountTypes[$mappedId] = $accountType;
            $configuration->setAccountTypes($accountTypes);

            Log::debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $accountType));

            return $accountType;
        }
        $type          = $configuration->getAccountTypes()[$mappedId] ?? 'expense';
        Log::debug(sprintf('Account type for Firefly III account #%d is "%s"', $mappedId, $type));

        return $type;
    }

    /**
     * @throws ImporterHttpException
     */
    private function getAccountType(int $accountId): string
    {
        $token   = SecretManager::getAccessToken();
        $url     = SecretManager::getBaseUrl();
        Log::debug(sprintf('Going to download account #%d', $accountId));
        $request = new GetAccountRequest($url, $token);
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setId($accountId);

        /** @var GetAccountResponse $result */
        try {
            $result = $request->get();
        } catch (ApiHttpException $e) {
            throw new ImporterHttpException($e->getMessage(), 0, $e);
        }
        $type    = $result->getAccount()->type;

        Log::debug(sprintf('Discovered that account #%d is of type "%s"', $accountId, $type));

        return $type;
    }

    /**
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
     * Handle transaction information when the amount is negative, and this is probably a withdrawal or a transfer.
     *
     * @throws ImporterHttpException
     */
    private function appendNegativeAmountInfo(int $accountId, array $transaction, Transaction $entry): array
    {
        $transaction['amount']    = bcmul($entry->amount, '-1');
        $transaction['source_id'] = (int)$this->accounts[$accountId]; // FIXME entry may not exist, then what?

        // append source iban and number (if present)
        $transaction              = $this->appendAccountFields($transaction, $entry, 'destination');

        $mappedId                 = null;
        if (isset($transaction['destination_name'])) {
            Log::debug(sprintf('Check if "%s" is mapped to an account by the user.', $transaction['destination_name']));
            $mappedId = $this->getMappedAccountId($transaction['destination_name']);
        }
        if (null === $mappedId) {
            Log::debug('Its not mapped by the user.');
        }

        if (null !== $mappedId && 0 !== $mappedId) {
            Log::debug(sprintf('Account name "%s" is mapped to Firefly III account ID "%d"', $transaction['destination_name'], $mappedId));
            $mappedType                    = $this->getMappedAccountType($mappedId);

            $originalDestName              = $transaction['destination_name'];
            $transaction['destination_id'] = $mappedId;

            // catch error here:
            try {
                $transaction['type'] = $this->getTransactionType('asset', $mappedType);
                Log::debug(sprintf('Transaction type seems to be %s', $transaction['type']));
            } catch (ImporterErrorException $e) {
                Log::error(sprintf('[%s]: %s', config('importer.version'), $e->getMessage()));
                Log::info('Will not use mapped ID, Firefly III account is of the wrong type.');
                unset($transaction['destination_id']);
                $transaction['destination_name'] = $originalDestName;
            }
        }

        $transaction              = $this->negativeTransactionSafetyCatch($transaction, (string)$entry->getDestinationName(), '');

        Log::debug(sprintf('source_id = %d, destination_id = "%s", destination_name = "%s", destination_iban = "%s"', $transaction['source_id'], $transaction['destination_id'] ?? '', $transaction['destination_name'] ?? '', $transaction['destination_iban'] ?? ''));

        return $transaction;
    }

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

    public function getTargetAccounts(): array
    {
        return $this->targetAccounts;
    }

    public function getUserAccounts(): array
    {
        return $this->userAccounts;
    }

    private function isAssetAccount(string $accountType, string $iban): bool
    {
        return 'asset' === $accountType && '' !== $iban && array_key_exists($iban, $this->targetAccounts);
    }

    private function ibanIsEmpty(string $iban): bool
    {
        return '' === $iban;
    }

    private function isExpenseOrRevenue(string $accountType, string $iban): bool
    {
        return ('revenue' === $accountType || 'expense' === $accountType) && '' !== $iban && (array_key_exists($iban, $this->expenseAccounts) || array_key_exists($iban, $this->revenueAccounts));
    }

    private function getRevenueOrExpenseName(string $iban, string $accountType): string
    {
        if ('revenue' === $accountType) {
            return $this->revenueAccountNames[$iban];
        }
        if ('expense' === $accountType) {
            return $this->expenseAccountNames[$iban];
        }

        return sprintf('(unknown %s account)', $accountType);
    }

    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob  = $importJob;
        $this->identifier = $importJob->identifier;
        $this->accounts   = $importJob->getConfiguration()->getAccounts();
        $this->importJob->refreshInstanceIdentifier();
    }
}
