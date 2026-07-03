<?php

/*
 * GetTransactionsRequest.php
 * Copyright (c) 2026 open-banking.io contribution to Firefly III (https://github.com/firefly-iii).
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

namespace App\Services\OpenBankingIo\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\OpenBankingIo\Response\GetTransactionsResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Throwable;

/**
 * Class GetTransactionsRequest
 *
 * Downloads and decrypts an account's statement, paging over the API until the whole set is
 * retrieved (or a safety cap is reached). Individual transactions that fail to decrypt or that
 * carry an unusable direction are skipped with a warning rather than aborting the import.
 */
final class GetTransactionsRequest extends Request
{
    private readonly string $from;
    private readonly string $to;

    /**
     * @param array{from?: string, to?: string} $opts
     */
    public function __construct(
        #[SensitiveParameter] string $apiKey,
        #[SensitiveParameter] string $privateKey,
        string $base,
        private readonly string $account,
        array $opts = []
    ) {
        $this->setApiKey($apiKey);
        $this->setPrivateKey($privateKey);
        $this->setBase($base);
        $this->from = array_key_exists('from', $opts) ? (string) $opts['from'] : '';
        $this->to   = array_key_exists('to', $opts) ? (string) $opts['to'] : '';
    }

    /**
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    public function get(): Response
    {
        $items   = true === config('importer.fake_data') ? $this->fakeData() : $this->downloadAllPages();

        $decoded = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = $this->decodeTransaction($item);
            if (null !== $row) {
                $decoded[] = $row;
            }
        }
        Log::debug(sprintf('open-banking.io: prepared %d transaction(s) for account %s.', count($decoded), $this->account));

        return new GetTransactionsResponse($decoded);
    }

    /**
     * Pages through the account statement until the full set is fetched or the safety cap is hit.
     *
     * @return array<int, mixed>
     *
     * @throws GuzzleException
     * @throws ImporterHttpException
     */
    private function downloadAllPages(): array
    {
        $pageSize = max(1, (int) config('obio.page_size'));
        $cap      = max($pageSize, (int) config('obio.max_transactions'));
        $all      = [];
        $offset   = 0;

        while (count($all) < $cap) {
            $this->setUrl($this->pagePath($offset, $pageSize));
            $page  = $this->authenticatedGet();
            $items = is_array($page['items'] ?? null) ? $page['items'] : [];
            $count = count($items);
            if (0 === $count) {
                break;
            }
            $all   = array_merge($all, $items);
            $offset += $count;

            $total = (int) ($page['total'] ?? 0);
            if ($count < $pageSize || ($total > 0 && $offset >= $total)) {
                break;
            }
        }

        if (count($all) >= $cap) {
            Log::warning(sprintf(
                'open-banking.io: reached the safety cap of %d transactions for account %s; increase OBIO_MAX_TRANSACTIONS to import more.',
                $cap,
                $this->account
            ));
            $all = array_slice($all, 0, $cap);
        }

        return $all;
    }

    private function pagePath(int $offset, int $limit): string
    {
        $query = ['limit' => (string) $limit, 'offset' => (string) $offset];
        if ('' !== $this->from) {
            $query['from'] = $this->from;
        }
        if ('' !== $this->to) {
            $query['to'] = $this->to;
        }

        return sprintf('api/accounts/%s/transactions?%s', rawurlencode($this->account), http_build_query($query));
    }

    /**
     * Decrypts a transaction and merges cleartext metadata into a flat array. Returns null (with a
     * warning) when the record cannot be decrypted or its direction/amount is unusable.
     *
     * @param array<string, mixed> $item
     *
     * @return null|array<string, mixed>
     */
    private function decodeTransaction(array $item): ?array
    {
        $id        = is_scalar($item['id'] ?? null) ? (string) $item['id'] : '(unknown)';

        try {
            $decrypted = true === config('importer.fake_data')
                ? $item
                : ($this->getEnvelope()->decryptToArray(is_string($item['enc'] ?? null) ? $item['enc'] : null) ?? []);
        } catch (Throwable $e) {
            Log::warning(sprintf('open-banking.io: skipping transaction "%s" that failed to decrypt: %s', $id, $e->getMessage()));

            return null;
        }

        $indicator = strtoupper((string) ($item['creditDebitIndicator'] ?? ''));
        if ('CRDT' !== $indicator && 'DBIT' !== $indicator) {
            Log::warning(sprintf('open-banking.io: skipping transaction "%s" with unknown credit/debit indicator "%s".', $id, $indicator));

            return null;
        }

        $amount    = ltrim((string) ($decrypted['amount'] ?? ''), '+');
        if (!is_numeric($amount)) {
            Log::warning(sprintf('open-banking.io: skipping transaction "%s" with a non-numeric amount "%s".', $id, $amount));

            return null;
        }

        return [
            'id'                    => $item['id'] ?? '',
            'accountId'             => $this->account,
            'currency'              => $item['currency'] ?? '',
            'creditDebitIndicator'  => $indicator,
            'status'                => $item['status'] ?? null,
            'bookingDate'           => $item['bookingDate'] ?? null,
            'valueDate'             => $item['valueDate'] ?? null,
            'transactionDate'       => $item['transactionDate'] ?? null,
            'amount'                => $amount,
            'creditorName'          => $decrypted['creditorName'] ?? null,
            'creditorIban'          => $decrypted['creditorIban'] ?? null,
            'debtorName'            => $decrypted['debtorName'] ?? null,
            'debtorIban'            => $decrypted['debtorIban'] ?? null,
            'remittanceInformation' => $decrypted['remittanceInformation'] ?? null,
            'note'                  => $decrypted['note'] ?? null,
            'referenceNumber'       => $decrypted['referenceNumber'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fakeData(): array
    {
        return [
            [
                'id'                    => 'obio-demo-txn-1',
                'currency'              => 'DKK',
                'creditDebitIndicator'  => 'DBIT',
                'bookingDate'           => '2026-06-08',
                'amount'                => '194.23',
                'creditorName'          => 'One.com',
                'remittanceInformation' => 'One.com',
            ],
            [
                'id'                    => 'obio-demo-txn-2',
                'currency'              => 'DKK',
                'creditDebitIndicator'  => 'CRDT',
                'bookingDate'           => '2026-06-07',
                'amount'                => '2500.00',
                'debtorName'            => 'Acme Payroll',
                'remittanceInformation' => 'Salary June',
            ],
        ];
    }

    public function post(): Response
    {
        throw new ImporterHttpException('Method not implemented');
    }

    public function put(): Response
    {
        throw new ImporterHttpException('Method not implemented');
    }
}
