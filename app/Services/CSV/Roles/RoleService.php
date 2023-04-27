<?php
/*
 * RoleService.php
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

namespace App\Services\CSV\Roles;

use App\Services\Camt\Transaction;
use App\Services\CSV\Specifics\SpecificInterface;
use App\Services\CSV\Specifics\SpecificService;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Storage\StorageService;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\Reader as CamtReader;
use InvalidArgumentException;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\UnableToProcessCsv;

/**
 * Class RoleService
 */
class RoleService
{
    public const EXAMPLE_COUNT  = 7;
    public const EXAMPLE_LENGTH = 26;

    /**
     * @param string        $content
     * @param Configuration $configuration
     *
     * @return array
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     */
    public static function getColumns(string $content, Configuration $configuration): array
    {
        $reader = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();
        switch ($delimiter) {
            default:
            case 'comma':
                $reader->setDelimiter(',');
                break;
            case 'semicolon':
                $reader->setDelimiter(';');
                break;
            case 'tab':
                $reader->setDelimiter("\t");
                break;
        }

        $headers = [];
        if (true === $configuration->isHeaders()) {
            try {
                $stmt    = (new Statement())->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $headers = $records->fetchOne();
                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                app('log')->error($e->getMessage());
                throw new InvalidArgumentException($e->getMessage());
            }
            // @codeCoverageIgnoreEnd
            app('log')->debug('Detected file headers:', $headers);
        }
        if (false === $configuration->isHeaders()) {
            app('log')->debug('Role service: file has no headers');
            try {
                $stmt    = (new Statement())->limit(1)->offset(0);
                $records = $stmt->process($reader);
                $count   = count($records->fetchOne());
                app('log')->debug(sprintf('Role service: first row has %d columns', $count));
                for ($i = 0; $i < $count; $i++) {
                    $headers[] = sprintf('Column #%d', $i + 1);
                }

                // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                app('log')->error($e->getMessage());
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        // specific processors may add or remove headers.
        // so those must be processed as well.
        // Fix as suggested by @FelikZ in https://github.com/firefly-iii/csv-importer/pull/4
        // TODO no longer used.
        /** @var string $name */
        foreach ($configuration->getSpecifics() as $name) {
            if (SpecificService::exists($name)) {
                /** @var SpecificInterface $object */
                $object  = app(SpecificService::fullClass($name));
                $headers = $object->runOnHeaders($headers);
            }
        }

        return $headers;
    }

    /**
     * @param string        $content
     * @param Configuration $configuration
     *
     * @return array
     */
    public static function getExampleDataFromCamt(string $content, Configuration $configuration): array
    {
        $camtReader   = new CamtReader(Config::getDefault());
        $camtMessage  = $camtReader->readString(StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE))); // -> Level A
        $transactions = [];
        $examples     = [
            'messageId'                                                                      => [],
            'messageCreationDate'                                                            => [],
            'statementId'                                                                    => [],
            'statementAccountIban'                                                           => [],
            'statementAccountNumber'                                                         => [],
            'statementCreationDate'                                                          => [],
            'entryDate'                                                                      => [],
            'entryAccountServicerReference'                                                  => [],
            'entryReference'                                                                 => [],
            'entryAdditionalInfo'                                                            => [],
            'entryAmount'                                                                    => [],
            'entryAmountCurrency'                                                            => [],
            'entryValueDate'                                                                 => [],
            'entryBookingDate'                                                               => [],
            'entryOpposingAccountNumber'                                                     => [],
            'entryOpposingName'                                                              => [],
            'entryBtcDomainCode'                                                             => [],
            'entryBtcFamilyCode'                                                             => [],
            'entryBtcSubFamilyCode'                                                          => [],
            'entryDetailOpposingAccountIban'                                                 => [],
            'entryDetailOpposingAccountNumber'                                               => [],
            'entryDetailOpposingName'                                                        => [],
            'entryDetailBtcDomainCode'                                                       => [],
            'entryDetailBtcFamilyCode'                                                       => [],
            'entryDetailBtcSubFamilyCode'                                                    => [],
            'entryDetailAmount'                                                              => [],
            'entryDetailAmountCurrency'                                                      => [],
            'entryDetailAccountServicerReference'                                            => [],
            'entryDetailRemittanceInformationUnstructuredBlockMessage'                       => [],
            'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation' => [],
        ];
        $statements   = $camtMessage->getRecords();
        /** @var CamtStatement $statement */
        foreach ($statements as $statement) { // -> Level B
            $entries = $statement->getEntries();
            /** @var Entry $entry */
            foreach ($entries as $entry) { // -> Level C
                $count = count($entry->getTransactionDetails()); // count level D entries.
                if (0 === $count) {
                    // TODO Create a single transaction, I guess?
                    $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry);
                }
                if (0 !== $count) {
                    foreach ($entry->getTransactionDetails() as $detail) {
                        $transactions[] = new Transaction($configuration, $camtMessage, $statement, $entry, $detail);
                    }
                }
            }
        }
        $count = 0;
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            if (5 === $count) {
                break;
            }
            $examples['messageId'][]                                                                      = $transaction->getField('messageId');
            $examples['messageCreationDate'][]                                                            = $transaction->getField('messageCreationDate');
            $examples['statementId'][]                                                                    = $transaction->getField('statementId');
            $examples['statementAccountIban'][]                                                           = $transaction->getField('statementAccountIban');
            $examples['statementAccountNumber'][]                                                         = $transaction->getField('statementAccountNumber');
            $examples['statementCreationDate'][]                                                          = $transaction->getField('statementCreationDate');
            $examples       ['entryDate'][]                                                               = $transaction->getField('entryDate');
            $examples       ['entryAccountServicerReference'][]                                           = $transaction->getField(
                'entryAccountServicerReference'
            );
            $examples     ['entryReference'][]                                                            = $transaction->getField('entryReference');
            $examples['entryAdditionalInfo'][]                                                            = $transaction->getField('entryAdditionalInfo');
            $examples['entryAmount'][]                                                                    = $transaction->getField('entryAmount');
            $examples['entryAmountCurrency'][]                                                            = $transaction->getField('entryAmountCurrency');
            $examples['entryValueDate'][]                                                                 = $transaction->getField('entryValueDate');
            $examples['entryBookingDate'][]                                                               = $transaction->getField('entryBookingDate');
            $examples['entryOpposingAccountNumber'][]                                                     = $transaction->getField('entryOpposingAccountNumber');
            $examples['entryOpposingName'][]                                                              = $transaction->getField('entryOpposingName');
            $examples['entryBtcDomainCode'][]                                                             = $transaction->getField('entryBtcDomainCode');
            $examples['entryBtcFamilyCode'][]                                                             = $transaction->getField('entryBtcFamilyCode');
            $examples['entryBtcSubFamilyCode'][]                                                          = $transaction->getField('entryBtcSubFamilyCode');
            $examples['entryDetailOpposingAccountIban'][]                                                 = $transaction->getField(
                'entryDetailOpposingAccountIban'
            );
            $examples['entryDetailOpposingAccountNumber'][]                                               = $transaction->getField(
                'entryDetailOpposingAccountNumber'
            );
            $examples['entryDetailOpposingName'][]                                                        = $transaction->getField('entryDetailOpposingName');
            $examples['entryDetailBtcDomainCode'][]                                                       = $transaction->getField('entryBtcDomainCode');
            $examples['entryDetailBtcFamilyCode'][]                                                       = $transaction->getField('entryDetailBtcFamilyCode');
            $examples['entryDetailBtcSubFamilyCode'][]                                                    = $transaction->getField('entryDetailBtcSubFamilyCode');
            $examples['entryDetailAmount'][]                                                              = $transaction->getField('entryDetailAmount');
            $examples['entryDetailAmountCurrency'][]                                                      = $transaction->getField('entryDetailAmountCurrency');
            $examples['entryDetailAccountServicerReference'][]                                            = $transaction->getField(
                'entryDetailAccountServicerReference'
            );
            $examples['entryDetailRemittanceInformationUnstructuredBlockMessage'][]                       = $transaction->getField(
                'entryDetailRemittanceInformationUnstructuredBlockMessage'
            );
            $examples['entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation'][] = $transaction->getField('entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation');
            $count++;
        }
        foreach($examples as $key => $list) {
            $examples[$key] = array_unique($list);
            $examples[$key] = array_filter($examples[$key], function(string $value) {
                return '' !== $value;
            });
        }
        return $examples;
    }

    /**
     * @param string        $content
     * @param Configuration $configuration
     *
     * @return array
     * @throws Exception
     */
    public static function getExampleData(string $content, Configuration $configuration): array
    {
        $reader = Reader::createFromString($content);

        // configure reader:
        $delimiter = $configuration->getDelimiter();
        switch ($delimiter) {
            default:
            case 'comma':
                $reader->setDelimiter(',');
                break;
            case 'semicolon':
                $reader->setDelimiter(';');
                break;
            case 'tab':
                $reader->setDelimiter("\t");
                break;
        }

        $offset   = $configuration->isHeaders() ? 1 : 0;
        $examples = [];
        // make statement.
        try {
            $stmt = (new Statement())->limit(self::EXAMPLE_COUNT)->offset($offset);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            app('log')->error($e->getMessage());
            throw new InvalidArgumentException($e->getMessage());
        }
        // @codeCoverageIgnoreEnd

        // grab the records:
        $records = $stmt->process($reader);
        /** @var array $line */
        foreach ($records as $line) {
            $line = array_values($line);
            //$line = SpecificService::runSpecifics($line, $configuration->getSpecifics());
            foreach ($line as $index => $cell) {
                if (strlen($cell) > self::EXAMPLE_LENGTH) {
                    $cell = sprintf('%s...', substr($cell, 0, self::EXAMPLE_LENGTH));
                }
                $examples[$index][] = $cell;
                $examples[$index]   = array_unique($examples[$index]);
            }
        }
        foreach ($examples as $line => $entries) {
            asort($entries);
            $examples[$line] = $entries;
        }

        return $examples;
    }
}
