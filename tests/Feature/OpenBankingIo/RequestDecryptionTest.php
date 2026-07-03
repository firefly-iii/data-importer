<?php

/*
 * RequestDecryptionTest.php
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

namespace Tests\Feature\OpenBankingIo;

use App\Services\OpenBankingIo\Model\Account;
use App\Services\OpenBankingIo\Model\Transaction;
use App\Services\OpenBankingIo\Request\GetAccountsRequest;
use App\Services\OpenBankingIo\Request\GetTransactionsRequest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * Feeds the real zero-knowledge "enc" API fixtures through the actual Request classes
 * (HTTP transport mocked with Guzzle) and asserts decryption, pagination and per-item
 * resilience -- i.e. the decrypt-in-Request wiring, not just the crypto in isolation.
 *
 * @internal
 *
 * @coversNothing
 */
final class RequestDecryptionTest extends TestCase
{
    private string $apiKey     = '';
    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['importer.fake_data' => false]);
        $creds            = $this->fixture('credentials.json');
        $this->apiKey     = (string) $creds['apiKey'];
        $this->privateKey = (string) $creds['encryptionKey']['privateKey'];
    }

    public function testGetAccountsDecryptsSensitiveFields(): void
    {
        $request  = $this->accountsRequest([(string) file_get_contents($this->fixturePath('api/accounts.json'))]);

        $accounts = iterator_to_array($request->get());
        $this->assertCount(1, $accounts);

        /** @var Account $account */
        $account  = $accounts[0];
        $this->assertSame('DK6466952001724927', $account->iban);            // decrypted from "enc"
        $this->assertSame('Tatic ApS', $account->ownerName);               // decrypted from "enc"
        $this->assertSame('Drift', $account->displayName);                // decrypted from "displayNameEnc"
        $this->assertSame('828.13', $account->getBookedBalance()?->amount); // decrypted balance "enc" (ITBD)
    }

    public function testGetTransactionsDecryptsAndSignsAmount(): void
    {
        $request      = $this->transactionsRequest([(string) file_get_contents($this->fixturePath('api/transactions.json'))]);

        $transactions = iterator_to_array($request->get());
        $this->assertCount(1, $transactions);

        /** @var Transaction $txn */
        $txn          = $transactions[0];
        $this->assertSame('33333333-3333-4333-8333-333333333333', $txn->getTransactionId());
        $this->assertSame('One.com', $txn->creditorName);                  // decrypted from "enc"
        $this->assertSame(-1, bccomp($txn->amount, '0'));                  // DBIT -> negative
        $this->assertSame(0, bccomp($txn->amount, '-194.23', 2));          // magnitude 194.23
    }

    public function testPaginationCollectsEveryPage(): void
    {
        config(['obio.page_size' => 2]);
        $tpl     = $this->transactionTemplate();

        // page 1: exactly page_size items (total=3) -> the loop must fetch again; page 2: 1 item.
        $page1   = (string) json_encode(['items' => [$this->withId($tpl, 'a'), $this->withId($tpl, 'b')], 'total' => 3]);
        $page2   = (string) json_encode(['items' => [$this->withId($tpl, 'c')], 'total' => 3]);

        $request = $this->transactionsRequest([$page1, $page2]);
        $txns    = iterator_to_array($request->get());

        $this->assertCount(3, $txns);
    }

    public function testSkipsUndecryptableAndInvalidRecords(): void
    {
        $tpl          = $this->transactionTemplate();
        $good         = $this->withId($tpl, 'good');
        $badEnvelope  = ['id' => 'bad', 'creditDebitIndicator' => 'DBIT', 'enc' => 'not-valid-base64!!'];
        $badIndicator = array_merge($tpl, ['id' => 'weird', 'creditDebitIndicator' => 'XXXX']);

        $body         = (string) json_encode(['items' => [$good, $badEnvelope, $badIndicator], 'total' => 3]);
        $request      = $this->transactionsRequest([$body]);

        $txns         = iterator_to_array($request->get());
        $this->assertCount(1, $txns, 'only the well-formed, decryptable transaction should survive');
        $this->assertSame('good', $txns[0]->getTransactionId());
    }

    // -- helpers ---------------------------------------------------------------

    /**
     * @param string[] $bodies
     */
    private function accountsRequest(array $bodies): GetAccountsRequest
    {
        $request = new GetAccountsRequest($this->apiKey, $this->privateKey, 'https://api.test');
        $request->setHandlerStack($this->mockStack($bodies));

        return $request;
    }

    /**
     * @param string[] $bodies
     */
    private function transactionsRequest(array $bodies): GetTransactionsRequest
    {
        $request = new GetTransactionsRequest($this->apiKey, $this->privateKey, 'https://api.test', 'acc-1');
        $request->setHandlerStack($this->mockStack($bodies));

        return $request;
    }

    /**
     * A shared handler stack whose queue advances across paged requests.
     *
     * @param string[] $bodies
     */
    private function mockStack(array $bodies): HandlerStack
    {
        return HandlerStack::create(new MockHandler(array_map(
            static fn (string $body): Response => new Response(200, [], $body),
            $bodies
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionTemplate(): array
    {
        return $this->fixture('api/transactions.json')['items'][0];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function withId(array $item, string $id): array
    {
        $item['id'] = $id;

        return $item;
    }

    private function fixturePath(string $name): string
    {
        return __DIR__.'/../../Unit/Services/OpenBankingIo/Crypto/fixtures/'.$name;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents($this->fixturePath($name)), true, 512, JSON_THROW_ON_ERROR);
    }
}
