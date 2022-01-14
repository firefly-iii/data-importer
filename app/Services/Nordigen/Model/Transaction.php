<?php
/*
 * Transaction.php
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


namespace App\Services\Nordigen\Model;

use Carbon\Carbon;
use DateTimeInterface;
use JsonException;
use Ramsey\Uuid\Uuid;

class Transaction
{
    public string  $additionalInformation;
    public string  $additionalInformationStructured;
    public Balance $balanceAfterTransaction;
    public string  $bankTransactionCode;
    public ?Carbon $bookingDate;
    public string  $checkId;
    public string  $creditorAgent;
    public string  $creditorId;
    public string  $creditorName;
    public array   $currencyExchange; // is an array (see https://github.com/firefly-iii/firefly-iii/issues/5286)
    // TODO use currency exchange info in notes
    public string  $debtorAgent;
    public string  $debtorName;
    public string  $entryReference;
    public string  $key;
    public string  $mandateId;
    public string  $proprietaryBank;
    public string  $purposeCode;
    public string  $remittanceInformationStructured;
    public array   $remittanceInformationStructuredArray;
    public string  $remittanceInformationUnstructured;
    public array   $remittanceInformationUnstructuredArray;
    public string  $transactionId;
    public string  $ultimateCreditor;
    public string  $ultimateDebtor;
    public ?Carbon $valueDate;

    // debtorAccount is an array, but is saved as strings
    // iban, currency
    public string $debtorAccountIban;
    public string $debtorAccountBban;
    public string $debtorAccountCurrency;

    // creditorAccount is an array, but saved as strings:
    // iban, currency
    public string $creditorAccountIban;
    public string $creditorAccountBban;
    public string $creditorAccountCurrency;

    // transactionAmount is an array, but is saved as strings
    // amount, currency
    public string $transactionAmount;
    public string $currencyCode;

    // my own custom fields
    public string $accountIdentifier;

    // undocumented fields
    public string $endToEndId;

    /**
     * Creates a transaction from a downloaded array.
     * @param $array
     * @return self
     */
    public static function fromArray($array): self
    {
        app('log')->debug('Nordigen transaction from array', $array);
        $object = new self;

        $object->additionalInformation                  = $array['additionalInformation'] ?? '';
        $object->additionalInformationStructured        = $array['additionalInformationStructured'] ?? '';
        $object->bankTransactionCode                    = $array['bankTransactionCode'] ?? '';
        $object->bookingDate                            = array_key_exists('bookingDate', $array) ? Carbon::createFromFormat('!Y-m-d', $array['bookingDate'], config('app.timezone')) : null;
        $object->key                                    = $array['key'] ?? '';
        $object->checkId                                = $array['checkId'] ?? '';
        $object->creditorAgent                          = $array['creditorAgent'] ?? '';
        $object->creditorId                             = $array['creditorId'] ?? '';
        $object->creditorName                           = $array['creditorName'] ?? '';
        $object->currencyExchange                       = $array['currencyExchange'] ?? [];
        $object->debtorAgent                            = $array['debtorAgent'] ?? '';
        $object->debtorName                             = $array['debtorName'] ?? '';
        $object->entryReference                         = $array['entryReference'] ?? '';
        $object->mandateId                              = $array['mandateId'] ?? '';
        $object->proprietaryBank                        = $array['proprietaryBank'] ?? '';
        $object->purposeCode                            = $array['purposeCode'] ?? '';
        $object->remittanceInformationStructured        = $array['remittanceInformationStructured'] ?? '';
        $object->remittanceInformationStructuredArray   = $array['remittanceInformationStructuredArray'] ?? [];
        $object->remittanceInformationUnstructured      = $array['remittanceInformationUnstructured'] ?? '';
        $object->remittanceInformationUnstructuredArray = $array['remittanceInformationUnstructuredArray'] ?? [];
        $object->transactionId                          = $array['transactionId'] ?? '';
        $object->ultimateCreditor                       = $array['ultimateCreditor'] ?? '';
        $object->ultimateDebtor                         = $array['ultimateDebtor'] ?? '';
        $object->valueDate                              = array_key_exists('valueDate', $array) ? Carbon::createFromFormat('!Y-m-d', $array['valueDate'], config('app.timezone')) : null;

        // undocumented values
        $object->endToEndId = $array['endToEndId'] ?? ''; // from Rabobank NL

        // models:
        if (array_key_exists('balanceAfterTransaction', $array) && is_array($array['balanceAfterTransaction'])) {
            $object->balanceAfterTransaction = Balance::createFromArray($array['balanceAfterTransaction'] ?? []);
        }
        if (array_key_exists('balanceAfterTransaction', $array) && !is_array($array['balanceAfterTransaction'])) {
            app('log')->warning(sprintf('balanceAfterTransaction is not an array: %s', $array['balanceAfterTransaction']));
            $object->balanceAfterTransaction = Balance::createFromArray([]);
        }
        if (!array_key_exists('balanceAfterTransaction', $array)) {
            $object->balanceAfterTransaction = Balance::createFromArray([]);
        }


        // array values:
        $object->creditorAccountIban     = $array['creditorAccount']['iban'] ?? '';
        $object->creditorAccountBban     = $array['creditorAccount']['bban'] ?? '';
        $object->creditorAccountCurrency = $array['creditorAccount']['currency'] ?? '';

        $object->debtorAccountIban     = $array['debtorAccount']['iban'] ?? '';
        $object->debtorAccountBban     = $array['debtorAccount']['bban'] ?? '';
        $object->debtorAccountCurrency = $array['debtorAccount']['currency'] ?? '';

        $object->transactionAmount = $array['transactionAmount']['amount'] ?? '';
        $object->currencyCode      = $array['transactionAmount']['currency'] ?? '';

        // other fields:
        $object->accountIdentifier = '';

        // generate transactionID if empty:
        if ('' === $object->transactionId) {
            $hash = hash('sha256', (string) microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                app('log')->error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = (string) Uuid::uuid5(config('importer.namespace'), $hash);
        }
        app('log')->debug(sprintf('Downloaded transaction with ID "%s"', $object->transactionId));

        return $object;
    }

    /**
     * @param array $array
     * @return static
     */
    public static function fromLocalArray(array $array): self
    {
        $object = new self;

        $object->additionalInformation                  = $array['additional_information'];
        $object->additionalInformationStructured        = $array['additional_information_structured'];
        $object->balanceAfterTransaction                = Balance::fromLocalArray($array['balance_after_transaction']);
        $object->bankTransactionCode                    = $array['bank_transaction_code'];
        $object->bookingDate                            = Carbon::createFromFormat(DateTimeInterface::W3C, $array['booking_date']);
        $object->checkId                                = $array['check_id'];
        $object->creditorAgent                          = $array['creditor_agent'];
        $object->creditorId                             = $array['creditor_id'];
        $object->creditorName                           = $array['creditor_name'];
        $object->currencyExchange                       = $array['currency_exchange'];
        $object->debtorAgent                            = $array['debtor_agent'];
        $object->debtorName                             = $array['debtor_name'];
        $object->entryReference                         = $array['entry_reference'];
        $object->key                                    = $array['key'];
        $object->mandateId                              = $array['mandate_id'];
        $object->proprietaryBank                        = $array['proprietary_bank'];
        $object->purposeCode                            = $array['purpose_code'];
        $object->remittanceInformationStructured        = $array['remittance_information_structured'];
        $object->remittanceInformationStructuredArray   = $array['remittance_information_structured_array'];
        $object->remittanceInformationUnstructured      = $array['remittance_information_unstructured'];
        $object->remittanceInformationUnstructuredArray = $array['remittance_information_unstructured_array'];
        $object->transactionId                          = $array['transaction_id'];
        $object->ultimateCreditor                       = $array['ultimate_creditor'];
        $object->ultimateDebtor                         = $array['ultimate_debtor'];
        $object->valueDate                              = Carbon::createFromFormat(DateTimeInterface::W3C, $array['value_date']);
        $object->transactionAmount                      = $array['transaction_amount']['amount'];
        $object->currencyCode                           = $array['transaction_amount']['currency'];
        $object->accountIdentifier                      = $array['account_identifier'];

        // undocumented values:
        $object->endToEndId = $array['end_to_end_id'];

        // TODO copy paste code.
        $object->debtorAccountIban   = array_key_exists('iban', $array['debtor_account']) ? $array['debtor_account']['iban'] : '';
        $object->creditorAccountIban = array_key_exists('iban', $array['creditor_account']) ? $array['creditor_account']['iban'] : '';

        $object->debtorAccountBban   = array_key_exists('bban', $array['debtor_account']) ? $array['debtor_account']['bban'] : '';
        $object->creditorAccountBban = array_key_exists('bban', $array['creditor_account']) ? $array['creditor_account']['bban'] : '';

        $object->debtorAccountCurrency   = array_key_exists('currency', $array['debtor_account']) ? $array['debtor_account']['currency'] : '';
        $object->creditorAccountCurrency = array_key_exists('currency', $array['creditor_account']) ? $array['creditor_account']['currency'] : '';

        //$object-> = $array[''];

        // generate transactionID if empty:
        if ('' === $object->transactionId) {
            $hash = hash('sha256', (string) microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                app('log')->error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = (string) Uuid::uuid5(config('importer.namespace'), $hash);
        }

        return $object;
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        if (null !== $this->bookingDate) {
            app('log')->debug('Returning book date');
            return $this->bookingDate;
        }
        if (null !== $this->valueDate) {
            app('log')->debug('Returning value date');
            return $this->valueDate;
        }
        app('log')->warning('Transaction has no date, return NOW.');
        return new Carbon(config('app.timezone'));
    }

    /**
     * Return transaction description, which depends on the values in the object:
     * @return string
     */
    public function getDescription(): string
    {
        $description = '';
        if ('' !== $this->remittanceInformationUnstructured) {
            $description = $this->remittanceInformationUnstructured;
            app('log')->debug('Description is now remittanceInformationUnstructured');
        }

        // try other values as well (Revolut)
        if ('' === $description && count($this->remittanceInformationUnstructuredArray) > 0) {
            $description = implode(' ', $this->remittanceInformationUnstructuredArray);
            app('log')->debug('Description is now remittanceInformationUnstructuredArray');
        }
        if ('' === $description) {
            app('log')->debug('Description is now remittanceInformationStructured');
            $description = $this->remittanceInformationStructured;
        }

        if ('' === $description) {
            app('log')->warning(sprintf('Transaction "%s" has no description.', $this->transactionId));
            $description = '(no description)';
        }
        return $description;
    }

    /**
     * Return name of the destination account
     *
     * @return string|null
     */
    public function getDestinationName(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorName) {
            app('log')->debug(sprintf('Destination name is "%s" (creditor)', $this->creditorName));
            return $this->creditorName;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination account name information.', $this->transactionId));
        return null;
    }

    /**
     * Return IBAN of the destination account
     *
     * @return string|null
     */
    public function getDestinationIban(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorAccountIban) {
            app('log')->debug(sprintf('Destination IBAN is "%s" (creditor)', $this->creditorAccountIban));
            return $this->creditorAccountIban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination IBAN information.', $this->transactionId));
        return null;
    }

    /**
     * Return IBAN of the destination account
     *
     * @return string|null
     */
    public function getDestinationNumber(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->creditorAccountBban) {
            app('log')->debug(sprintf('Destination BBAN is "%s" (creditor)', $this->creditorAccountBban));
            return $this->creditorAccountBban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no destination BBAN information.', $this->transactionId));
        return null;
    }

    /**
     * Return name of the source account.
     *
     * @return string|null
     */
    public function getSourceName(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorName) {
            app('log')->debug(sprintf('Source name is "%s" (debtor)', $this->debtorName));
            return $this->debtorName;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source account name information.', $this->transactionId));
        return null;
    }

    /**
     * Return IBAN of the source account.
     *
     * @return string|null
     */
    public function getSourceIban(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorAccountIban) {
            app('log')->debug(sprintf('Source IBAN is "%s" (debtor)', $this->debtorAccountIban));
            return $this->debtorAccountIban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source IBAN information.', $this->transactionId));
        return null;
    }

    /**
     * Return account number of the source account.
     *
     * @return string|null
     */
    public function getSourceNumber(): ?string
    {
        app('log')->debug(__METHOD__);
        if ('' !== $this->debtorAccountBban) {
            app('log')->debug(sprintf('Source BBAN is "%s" (debtor)', $this->debtorAccountBban));
            return $this->debtorAccountBban;
        }
        app('log')->warning(sprintf('Transaction "%s" has no source BBAN information.', $this->transactionId));
        return null;
    }

    /**
     * Call this "toLocalArray" because we want to confusion with "fromArray", which is really based
     * on Nordigen information. Likewise there is also "fromLocalArray".
     * @return array
     */
    public function toLocalArray(): array
    {
        $return = [
            'additional_information'                    => $this->additionalInformation,
            'additional_information_structured'         => $this->additionalInformationStructured,
            'balance_after_transaction'                 => $this->balanceAfterTransaction->toLocalArray(),
            'bank_transaction_code'                     => $this->bankTransactionCode,
            'booking_date'                              => $this->bookingDate->toW3cString(),
            'check_id'                                  => $this->checkId,
            'creditor_agent'                            => $this->creditorAgent,
            'creditor_id'                               => $this->creditorId,
            'creditor_name'                             => $this->creditorName,
            'currency_exchange'                         => $this->currencyExchange,
            'debtor_agent'                              => $this->debtorAgent,
            'debtor_name'                               => $this->debtorName,
            'entry_reference'                           => $this->entryReference,
            'key'                                       => $this->key,
            'mandate_id'                                => $this->mandateId,
            'proprietary_bank'                          => $this->proprietaryBank,
            'purpose_code'                              => $this->purposeCode,
            'remittance_information_structured'         => $this->remittanceInformationStructured,
            'remittance_information_structured_array'   => $this->remittanceInformationStructuredArray,
            'remittance_information_unstructured'       => $this->remittanceInformationUnstructured,
            'remittance_information_unstructured_array' => $this->remittanceInformationUnstructuredArray,
            'transaction_id'                            => $this->transactionId,
            'ultimate_creditor'                         => $this->ultimateCreditor,
            'ultimate_debtor'                           => $this->ultimateDebtor,
            'value_date'                                => $this->valueDate->toW3cString(),
            'account_identifier'                        => $this->accountIdentifier,
            // array values:
            'debtor_account'                            => [
                'iban'     => $this->debtorAccountIban,
                'currency' => $this->debtorAccountCurrency,
            ],
            'creditor_account'                          => [
                'iban'     => $this->creditorAccountIban,
                'currency' => $this->creditorAccountCurrency,
            ],
            'transaction_amount'                        => [
                'amount'   => $this->transactionAmount,
                'currency' => $this->currencyCode,
            ],

            // undocumented values:
            'end_to_end_id'                             => $this->endToEndId,
        ];

        return $return;
    }
}
