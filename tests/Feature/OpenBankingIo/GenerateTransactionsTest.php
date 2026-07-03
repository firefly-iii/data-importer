<?php

/*
 * GenerateTransactionsTest.php
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

use App\Services\OpenBankingIo\Conversion\Routine\GenerateTransactions;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Guards the transfer-detection logic: a counterparty IBAN that resolves to the SAME Firefly III
 * account must NOT become an A->A transfer (which Firefly III rejects), while a genuinely different
 * asset account must.
 *
 * @internal
 *
 * @coversNothing
 */
final class GenerateTransactionsTest extends TestCase
{
    /**
     * @param array<string, int> $targetAccounts
     *
     * @return array<string, mixed>
     */
    private function appendCounterparty(array $targetAccounts, string $side, string $name, ?string $iban, int $ownFireflyId): array
    {
        $generator = new GenerateTransactions();

        $property  = new ReflectionProperty(GenerateTransactions::class, 'targetAccounts');
        $property->setValue($generator, $targetAccounts);

        $method    = new ReflectionMethod(GenerateTransactions::class, 'appendCounterparty');

        return $method->invoke($generator, ['type' => 'deposit'], $side, $name, $iban, $ownFireflyId);
    }

    public function testCounterpartyOwnIbanIsNotATransfer(): void
    {
        // The counterparty IBAN maps to Firefly account #5, which is ALSO this transaction's account.
        $result = $this->appendCounterparty(['DK-OWN' => 5, 'DK-OTHER' => 7], 'source', 'Acme', 'DK-OWN', 5);

        $this->assertSame('deposit', $result['type'], 'own-IBAN booking must stay a deposit, not a transfer');
        $this->assertArrayNotHasKey('source_id', $result, 'must not link source to the same account');
        $this->assertSame('Acme', $result['source_name']);
        $this->assertSame('DK-OWN', $result['source_iban']);
    }

    public function testCounterpartyDifferentAssetAccountIsATransfer(): void
    {
        // The counterparty IBAN maps to Firefly account #7, different from this account (#5).
        $result = $this->appendCounterparty(['DK-OWN' => 5, 'DK-OTHER' => 7], 'source', 'Acme', 'DK-OTHER', 5);

        $this->assertSame('transfer', $result['type']);
        $this->assertSame(7, $result['source_id']);
        $this->assertArrayNotHasKey('source_name', $result);
    }

    public function testUnknownCounterpartyIbanFallsBackToName(): void
    {
        $result = $this->appendCounterparty(['DK-OWN' => 5], 'destination', 'One.com', 'DK-STRANGER', 5);

        $this->assertSame('deposit', $result['type']);
        $this->assertArrayNotHasKey('destination_id', $result);
        $this->assertSame('One.com', $result['destination_name']);
        $this->assertSame('DK-STRANGER', $result['destination_iban']);
    }

    public function testMissingNameGetsPlaceholder(): void
    {
        $result = $this->appendCounterparty([], 'source', '', null, 5);

        $this->assertSame('(unknown source account)', $result['source_name']);
        $this->assertArrayNotHasKey('source_iban', $result);
    }
}
