<?php

/*
 * GetAccountsRequest.php
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
use App\Services\OpenBankingIo\Response\ErrorResponse;
use App\Services\OpenBankingIo\Response\GetAccountsResponse;
use App\Services\Shared\Response\Response;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Throwable;

/**
 * Class GetAccountsRequest
 *
 * Lists the user's connected accounts, decrypting each account's zero-knowledge envelope.
 * A single account that fails to decrypt is skipped (with a warning) rather than aborting the list.
 */
final class GetAccountsRequest extends Request
{
    public function __construct(#[SensitiveParameter] string $apiKey, #[SensitiveParameter] string $privateKey, string $base)
    {
        $this->setUrl('api/accounts');
        $this->setApiKey($apiKey);
        $this->setPrivateKey($privateKey);
        $this->setBase($base);
    }

    /**
     * @throws GuzzleException
     */
    public function get(): Response
    {
        Log::debug('open-banking.io GetAccountsRequest::get()');

        if (true === config('importer.fake_data')) {
            return new GetAccountsResponse($this->fakeData());
        }

        try {
            $wires = $this->authenticatedGet();
        } catch (ImporterHttpException $e) {
            return new ErrorResponse(['statusCode' => $e->statusCode]);
        }

        $decoded = [];
        foreach ($wires as $wire) {
            if (!is_array($wire)) {
                continue;
            }

            try {
                $decoded[] = $this->decryptAccount($wire);
            } catch (Throwable $e) {
                // Fail closed per-item: skip the account that could not be decrypted, keep the rest.
                Log::warning(sprintf(
                    'open-banking.io: skipping account "%s" that failed to decrypt: %s',
                    is_scalar($wire['id'] ?? null) ? (string) $wire['id'] : '(unknown)',
                    $e->getMessage()
                ));
            }
        }

        return new GetAccountsResponse($decoded);
    }

    /**
     * Decrypts an account wire (enc, displayNameEnc, balances[].enc) into a plain array.
     *
     * @param array<string, mixed> $wire
     *
     * @return array<string, mixed>
     */
    private function decryptAccount(array $wire): array
    {
        $envelope = $this->getEnvelope();
        $account  = $envelope->decryptToArray(is_string($wire['enc'] ?? null) ? $wire['enc'] : null) ?? [];
        $name     = $envelope->decryptToArray(is_string($wire['displayNameEnc'] ?? null) ? $wire['displayNameEnc'] : null) ?? [];

        $balances = [];
        foreach ((is_array($wire['balances'] ?? null) ? $wire['balances'] : []) as $balance) {
            $balance    = is_array($balance) ? $balance : [];
            $decrypted  = $envelope->decryptToArray(is_string($balance['enc'] ?? null) ? $balance['enc'] : null) ?? [];
            $balances[] = [
                'type'          => $balance['type'] ?? '',
                'currency'      => $balance['currency'] ?? ($wire['currency'] ?? ''),
                'referenceDate' => $balance['referenceDate'] ?? null,
                'name'          => $decrypted['name'] ?? null,
                'amount'        => $decrypted['amount'] ?? '0',
            ];
        }

        return [
            'id'             => $wire['id'] ?? '',
            'aspspName'      => $wire['aspspName'] ?? '',
            'aspspCountry'   => $wire['aspspCountry'] ?? '',
            'currency'       => $wire['currency'] ?? '',
            'accountType'    => $wire['accountType'] ?? null,
            'bic'            => $wire['bic'] ?? null,
            'needsReconnect' => $wire['needsReconnect'] ?? false,
            'iban'           => $account['iban'] ?? null,
            'bban'           => $account['bban'] ?? null,
            'ownerName'      => $account['ownerName'] ?? null,
            'accountName'    => $account['accountName'] ?? null,
            'product'        => $account['product'] ?? null,
            'displayName'    => $name['displayName'] ?? null,
            'balances'       => $balances,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fakeData(): array
    {
        return [
            [
                'id'           => 'obio-demo-account-1',
                'aspspName'    => 'Sandbox Finance',
                'aspspCountry' => 'DK',
                'currency'     => 'DKK',
                'accountType'  => 'CACC',
                'iban'         => 'DK6466952001724927',
                'bban'         => '66952001724927',
                'ownerName'    => 'Tatic ApS',
                'accountName'  => 'Erhverv',
                'displayName'  => 'Drift',
                'balances'     => [
                    ['type' => 'ITBD', 'currency' => 'DKK', 'name' => 'Tatic', 'amount' => '828.13'],
                ],
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
