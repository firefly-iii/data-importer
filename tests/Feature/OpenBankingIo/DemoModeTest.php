<?php

/*
 * DemoModeTest.php
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
use App\Services\OpenBankingIo\Response\GetAccountsResponse;
use App\Services\OpenBankingIo\Response\GetTransactionsResponse;
use Tests\TestCase;

/**
 * Exercises the open-banking.io provider through its demo (fake_data) path:
 * requests -> responses -> models, without any network or private key.
 *
 * @internal
 *
 * @coversNothing
 */
final class DemoModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['importer.fake_data' => true]);
    }

    public function testDemoAccountsAreListed(): void
    {
        $request  = new GetAccountsRequest('demo-key', '', 'https://demo.open-banking.io');
        $response = $request->get();

        $this->assertInstanceOf(GetAccountsResponse::class, $response);
        $this->assertCount(1, $response);

        /** @var Account $account */
        $account  = iterator_to_array($response)[0];
        $this->assertSame('DK6466952001724927', $account->iban);
        $this->assertSame('Drift', $account->displayName);
        $this->assertSame('828.13', $account->getBookedBalance()?->amount);
    }

    public function testDemoTransactionsCarrySignAndExternalId(): void
    {
        $request      = new GetTransactionsRequest('demo-key', '', 'https://demo.open-banking.io', 'obio-demo-account-1');
        $response     = $request->get();

        $this->assertInstanceOf(GetTransactionsResponse::class, $response);
        $this->assertCount(2, $response);

        /** @var Transaction[] $transactions */
        $transactions = iterator_to_array($response);

        // DBIT -> negative signed amount, external id set to the source transaction id.
        $this->assertSame('obio-demo-txn-1', $transactions[0]->getTransactionId());
        $this->assertSame('DBIT', $transactions[0]->creditDebitIndicator);
        $this->assertSame(-1, bccomp($transactions[0]->amount, '0'));
        $this->assertSame('One.com', $transactions[0]->getDestinationName());

        // CRDT -> positive signed amount.
        $this->assertSame('CRDT', $transactions[1]->creditDebitIndicator);
        $this->assertSame(1, bccomp($transactions[1]->amount, '0'));
        $this->assertSame('Acme Payroll', $transactions[1]->getSourceName());
    }
}
