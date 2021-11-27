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
use Log;
use Ramsey\Uuid\Uuid;

class Transaction
{
    public string  $additionalInformation;
    public string  $additionalInformationStructured;
    public string  $balanceAfterTransaction;
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
    public string $debtorAccountCurrency;

    // creditorAccount is an array, but saved as strings:
    // iban, currency
    public string $creditorAccountIban;
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
        //Log::debug('Transaction from array', $array);
        $object = new self;

        $object->additionalInformation                  = $array['additionalInformation'] ?? '';
        $object->additionalInformationStructured        = $array['additionalInformationStructured'] ?? '';
        $object->balanceAfterTransaction                = $array['balanceAfterTransaction'] ?? '';
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


        // array values:
        $object->creditorAccountIban     = $array['creditorAccount']['iban'] ?? '';
        $object->creditorAccountCurrency = $array['creditorAccount']['currency'] ?? '';

        $object->debtorAccountIban     = $array['debtorAccount']['iban'] ?? '';
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
                Log::error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = (string) Uuid::uuid5(config('importer.namespace'), $hash);
        }
        Log::debug(sprintf('Downloaded transaction with ID "%s"', $object->transactionId));

        return $object;
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        if (null !== $this->valueDate) {
            return $this->valueDate;
        }
        if (null !== $this->bookingDate) {
            return $this->bookingDate;
        }
        Log::warning('Transaction has no date, return NOW.');
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
        }

        // try other values as well (Revolut)
        if ('' === $description && count($this->remittanceInformationUnstructuredArray) > 0) {
            $description = implode(' ', $this->remittanceInformationUnstructuredArray);
        }

        if ('' === $description) {
            Log::warning(sprintf('Transaction "%s" has no description.', $this->transactionId));
            $description = '(no description)';
        }
        return $description;
    }

    /**
     * Return name of the destination account. Depends also on the amount
     *
     * @return string|null
     */
    public function getDestinationName(): ?string
    {
        if (1 === bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Destination name is "debtor" because %s > 0.', $this->transactionAmount));
            // amount is positive, its a deposit, return creditor
            if ('' !== $this->debtorName) {
                Log::debug(sprintf('Destination name is "%s"', $this->debtorName));
                return $this->debtorName;
            }
        }
        if (1 !== bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Destination name is "creditor" because %s < 0.', $this->transactionAmount));
            if ('' !== $this->creditorName) {
                Log::debug(sprintf('Destination name is "%s"', $this->creditorName));
                return $this->creditorName;
            }
        }

        Log::warning(sprintf('Transaction "%s" has no destination account information.', $this->transactionId));
        return null;
    }

    /**
     * Return IBAN of the destination account. Depends also on the amount
     *
     * @return string|null
     */
    public function getDestinationIban(): ?string
    {
        if (1 === bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Destination IBAN is "debtor" because %s > 0.', $this->transactionAmount));
            // amount is positive, its a deposit, return creditor
            if ('' !== $this->debtorAccountIban) {
                Log::debug(sprintf('Destination IBAN is "%s"', $this->debtorAccountIban));
                return $this->debtorAccountIban;
            }
        }
        if (1 !== bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Destination IBAN is "creditor" because %s < 0.', $this->transactionAmount));
            if ('' !== $this->creditorAccountIban) {
                Log::debug(sprintf('Destination IBAN is "%s"', $this->creditorAccountIban));
                return $this->creditorAccountIban;
            }
        }

        Log::warning(sprintf('Transaction "%s" has no destination IBAN.', $this->transactionId));
        return null;
    }


    /**
     * Return name of the source account. Depends also on the amount
     *
     * @return string|null
     */
    public function getSourceName(): ?string
    {
        if (-1 === bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Source name is "debtor" because %s < 0.', $this->transactionAmount));
            // amount is positive, its a deposit, return creditor
            if ('' !== $this->debtorName) {
                Log::debug(sprintf('Source name is "%s"', $this->debtorName));
                return $this->debtorName;
            }
        }
        if (-1 !== bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Source name is "creditor" because %s > 0.', $this->transactionAmount));
            if ('' !== $this->creditorName) {
                Log::debug(sprintf('Source name is "%s"', $this->creditorName));
                return $this->creditorName;
            }
        }

        Log::warning(sprintf('Transaction "%s" has no source account information.', $this->transactionId));
        return null;
    }

    /**
     * Return name of the source account. Depends also on the amount
     *
     * @return string|null
     */
    public function getSourceIban(): ?string
    {
        if (-1 === bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Source IBAN is from "debtor" because %s < 0.', $this->transactionAmount));
            // amount is positive, its a deposit, return creditor
            if ('' !== $this->debtorAccountIban) {
                Log::debug(sprintf('Source IBAN is "%s"', $this->debtorAccountIban));
                return $this->debtorAccountIban;
            }
        }
        if (-1 !== bccomp($this->transactionAmount, '0')) {
            Log::debug(sprintf('Source IBAN is "creditor" because %s > 0.', $this->transactionAmount));
            if ('' !== $this->creditorAccountIban) {
                Log::debug(sprintf('Source IBAN is "%s"', $this->creditorAccountIban));
                return $this->creditorAccountIban;
            }
        }

        Log::warning(sprintf('Transaction "%s" has no source IBAN information.', $this->transactionId));
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
            'balance_after_transaction'                 => $this->balanceAfterTransaction,
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

    /**
     * @param array $array
     * @return static
     */
    public static function fromLocalArray(array $array): self
    {
        $object = new self;

        $object->additionalInformation                  = $array['additional_information'];
        $object->additionalInformationStructured        = $array['additional_information_structured'];
        $object->balanceAfterTransaction                = $array['balance_after_transaction'];
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

        $object->debtorAccountCurrency   = array_key_exists('currency', $array['debtor_account']) ? $array['debtor_account']['currency'] : '';
        $object->creditorAccountCurrency = array_key_exists('currency', $array['creditor_account']) ? $array['creditor_account']['currency'] : '';

        //$object-> = $array[''];

        // generate transactionID if empty:
        if ('' === $object->transactionId) {
            $hash = hash('sha256', (string) microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                Log::error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $object->transactionId = Uuid::uuid5(config('importer.namespace'), $hash);
        }

        return $object;
    }
}
