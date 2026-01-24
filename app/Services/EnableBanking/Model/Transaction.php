<?php

/*
 * Transaction.php
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

namespace App\Services\EnableBanking\Model;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use JsonException;
use Ramsey\Uuid\Uuid;

/**
 * Class Transaction
 */
class Transaction
{
    public string $transactionId = '';
    public string $accountUid = '';
    public string $transactionAmount = '';
    public string $currencyCode = '';
    public ?Carbon $bookingDate = null;
    public ?Carbon $valueDate = null;
    public string $creditorName = '';
    public string $creditorIban = '';
    public string $debtorName = '';
    public string $debtorIban = '';
    public string $remittanceInformation = '';
    public string $additionalInformation = '';
    public string $status = '';
    public array $tags = [];

    public static function fromArray(array $array): self
    {
        Log::debug('Enable Banking transaction from array', $array);

        $transaction = new self();
        $transaction->transactionId = $array['transaction_id'] ?? '';
        $transaction->accountUid = $array['account_uid'] ?? '';

        // Handle transaction amount
        $transaction->transactionAmount = (string) ($array['transaction_amount']['amount'] ?? '0');
        $transaction->currencyCode = $array['transaction_amount']['currency'] ?? '';

        // Handle dates
        if (isset($array['booking_date'])) {
            $transaction->bookingDate = Carbon::parse($array['booking_date']);
        }
        if (isset($array['value_date'])) {
            $transaction->valueDate = Carbon::parse($array['value_date']);
        }

        // Creditor info
        $transaction->creditorName = $array['creditor_name'] ?? $array['creditor']['name'] ?? '';
        $transaction->creditorIban = $array['creditor_account']['iban'] ?? '';

        // Debtor info
        $transaction->debtorName = $array['debtor_name'] ?? $array['debtor']['name'] ?? '';
        $transaction->debtorIban = $array['debtor_account']['iban'] ?? '';

        // Description
        $transaction->remittanceInformation = $array['remittance_information'] ?? '';
        if (is_array($transaction->remittanceInformation)) {
            $transaction->remittanceInformation = implode(' ', $transaction->remittanceInformation);
        }
        $transaction->additionalInformation = $array['additional_information'] ?? '';

        $transaction->status = $array['status'] ?? 'booked';

        // Add status as tag
        if ('' !== $transaction->status) {
            $transaction->tags[] = $transaction->status;
        }

        // Generate transaction ID if empty
        if ('' === $transaction->transactionId) {
            $hash = hash('sha256', (string) microtime());
            try {
                $hash = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                Log::error(sprintf('Could not parse array into JSON: %s', $e->getMessage()));
            }
            $transaction->transactionId = sprintf('eb-%s', Uuid::uuid5(config('importer.namespace'), $hash));
        }

        return $transaction;
    }

    public function getDate(): Carbon
    {
        if ($this->bookingDate instanceof Carbon) {
            return $this->bookingDate;
        }
        if ($this->valueDate instanceof Carbon) {
            return $this->valueDate;
        }
        Log::warning('Transaction has no date, return NOW.');

        return Carbon::now(config('app.timezone'));
    }

    public function getValueDate(): ?Carbon
    {
        return $this->valueDate;
    }

    public function getDescription(): string
    {
        if ('' !== $this->remittanceInformation) {
            return $this->remittanceInformation;
        }
        if ('' !== $this->additionalInformation) {
            return $this->additionalInformation;
        }
        Log::warning(sprintf('Transaction "%s" has no description.', $this->transactionId));

        return '(no description)';
    }

    public function getCleanDescription(): string
    {
        $description = $this->getDescription();
        $description = str_replace(["\n", "\t", "\r"], ' ', $description);

        return trim($description);
    }

    public function getTransactionId(): string
    {
        $accountId = substr(trim((string) preg_replace('/\s+/', ' ', $this->accountUid)), 0, 125);
        $transactionId = substr(trim((string) preg_replace('/\s+/', ' ', $this->transactionId)), 0, 125);

        return trim(sprintf('%s-%s', $accountId, $transactionId));
    }

    public function getSourceName(): ?string
    {
        if ('' !== $this->debtorName) {
            return $this->debtorName;
        }

        return null;
    }

    public function getSourceIban(): ?string
    {
        if ('' !== $this->debtorIban) {
            return $this->debtorIban;
        }

        return null;
    }

    public function getDestinationName(): ?string
    {
        if ('' !== $this->creditorName) {
            return $this->creditorName;
        }

        return null;
    }

    public function getDestinationIban(): ?string
    {
        if ('' !== $this->creditorIban) {
            return $this->creditorIban;
        }

        return null;
    }

    public function getNotes(): string
    {
        $notes = '';
        if ('' !== $this->additionalInformation && $this->additionalInformation !== $this->remittanceInformation) {
            $notes = $this->additionalInformation;
        }

        return trim($notes);
    }

    public function toLocalArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'account_uid' => $this->accountUid,
            'transaction_amount' => $this->transactionAmount,
            'currency_code' => $this->currencyCode,
            'booking_date' => $this->bookingDate?->toW3cString(),
            'value_date' => $this->valueDate?->toW3cString(),
            'creditor_name' => $this->creditorName,
            'creditor_iban' => $this->creditorIban,
            'debtor_name' => $this->debtorName,
            'debtor_iban' => $this->debtorIban,
            'remittance_information' => $this->remittanceInformation,
            'additional_information' => $this->additionalInformation,
            'status' => $this->status,
            'tags' => $this->tags,
        ];
    }
}
