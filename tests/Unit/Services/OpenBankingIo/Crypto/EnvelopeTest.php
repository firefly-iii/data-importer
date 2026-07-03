<?php

/*
 * EnvelopeTest.php
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

namespace Tests\Unit\Services\OpenBankingIo\Crypto;

use App\Services\OpenBankingIo\Crypto\Envelope;
use App\Services\OpenBankingIo\Crypto\EnvelopeException;
use PHPUnit\Framework\TestCase;

/**
 * Wire-format interop test for the zero-knowledge Envelope decryptor.
 *
 * Uses the cross-language shared fixtures from the official open-banking.io clients,
 * asserting the ported in-repo Envelope decrypts them to the exact expected plaintext.
 *
 * @internal
 *
 * @covers \App\Services\OpenBankingIo\Crypto\Envelope
 */
final class EnvelopeTest extends TestCase
{
    private Envelope $envelope;

    /** @var array<string, string> */
    private array $envelopes;

    /** @var array<string, mixed> */
    private array $expected;

    protected function setUp(): void
    {
        parent::setUp();
        $keypair         = $this->fixture('keypair.json');
        $this->envelope  = Envelope::fromPkcs8Base64((string) $keypair['privateKeyPkcs8B64']);
        $this->envelopes = $this->fixture('envelopes.json');
        $this->expected  = $this->fixture('expected.json');
    }

    public function testAccountEnvelopeDecrypts(): void
    {
        $account = $this->envelope->decryptToArray($this->envelopes['account']);
        $this->assertNotNull($account);
        $this->assertSame($this->expected['account'], $account);
        $this->assertSame('DK6466952001724927', $account['iban']);
    }

    public function testDisplayNameEnvelopeDecrypts(): void
    {
        $name = $this->envelope->decryptToArray($this->envelopes['displayName']);
        $this->assertNotNull($name);
        $this->assertSame('Drift', $name['displayName']);
    }

    public function testBalanceEnvelopeDecrypts(): void
    {
        $balance = $this->envelope->decryptToArray($this->envelopes['balance']);
        $this->assertNotNull($balance);
        $this->assertSame('828.13', $balance['amount']);
    }

    public function testTransactionEnvelopeDecrypts(): void
    {
        $txn = $this->envelope->decryptToArray($this->envelopes['transaction']);
        $this->assertNotNull($txn);
        $this->assertSame('194.23', $txn['amount']);
        $this->assertSame('One.com', $txn['creditorName']);
    }

    public function testUidEnvelopeDecrypts(): void
    {
        $uid = $this->envelope->decryptToArray($this->envelopes['uid']);
        $this->assertNotNull($uid);
        $this->assertSame('c5d93aa7-5e23-4da0-ba88-42b9a584492c', $uid['uid']);
    }

    public function testNullAndEmptyYieldNull(): void
    {
        $this->assertNull($this->envelope->decryptToArray(null));
        $this->assertNull($this->envelope->decryptToArray(''));
    }

    public function testWrongKeyFailsAuthTag(): void
    {
        $other = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertNotFalse($other);
        $pem   = '';
        openssl_pkey_export($other, $pem);
        $b64   = (string) preg_replace('/-----[^-]+-----|\s+/', '', (string) $pem);

        $wrong = Envelope::fromPkcs8Base64($b64);
        $this->expectException(EnvelopeException::class);
        $wrong->decryptToArray($this->envelopes['account']);
    }

    public function testDecryptsRealApiAccountEnvelope(): void
    {
        // Proves the Request-layer path: decrypt an account "enc" from the API fixture
        // using the private key from the credentials bundle.
        $credentials = $this->fixture('credentials.json');
        $envelope    = Envelope::fromPkcs8Base64((string) $credentials['encryptionKey']['privateKey']);
        $accounts    = $this->fixture('api/accounts.json');

        $decrypted   = $envelope->decryptToArray($accounts[0]['enc']);
        $this->assertNotNull($decrypted);
        $this->assertSame('DK6466952001724927', $decrypted['iban']);
        $this->assertSame('Tatic ApS', $decrypted['ownerName']);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function fixture(string $name): array
    {
        $raw = file_get_contents(__DIR__.'/fixtures/'.$name);
        $this->assertIsString($raw, sprintf('Missing fixture: %s', $name));

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
