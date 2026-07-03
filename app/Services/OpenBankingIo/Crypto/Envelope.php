<?php

/*
 * Envelope.php
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

namespace App\Services\OpenBankingIo\Crypto;

use OpenSSLAsymmetricKey;

/**
 * Decrypts open-banking.io's zero-knowledge data envelopes locally.
 *
 * Scheme: ephemeral ECDH on NIST P-256 -> HKDF-SHA256 -> AES-256-GCM.
 * Wire: version(1)=0x01 | ephemeralPublicKeyRaw(65) | nonce(12) | tag(16) | ciphertext.
 * Only the user's private key can decrypt -- the service stores ciphertext it cannot read.
 *
 * Ported from the official open-banking.io PHP client (open-banking-io/client, MIT),
 * so the data importer carries no extra Composer runtime dependency.
 */
final class Envelope
{
    private const int VERSION            = 0x01;
    private const int POINT_LEN          = 65;
    private const int NONCE_LEN          = 12;
    private const int TAG_LEN            = 16;
    private const int HEADER_LEN         = 1 + self::POINT_LEN + self::NONCE_LEN + self::TAG_LEN;
    private const string HKDF_INFO       = 'bank.core.ci/zk/v1';

    /**
     * Fixed P-256 SubjectPublicKeyInfo DER prefix (26 bytes). The raw 65-byte point
     * (which itself begins with 0x04) is appended to build a parsable SPKI structure.
     */
    private const string SPKI_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    private function __construct(private readonly OpenSSLAsymmetricKey $privateKey) {}

    /**
     * Loads a base64 PKCS#8 EC (P-256) private key by wrapping the DER in PEM.
     */
    public static function fromPkcs8Base64(string $privateKeyPkcs8B64): self
    {
        $pem     = "-----BEGIN PRIVATE KEY-----\n"
            .chunk_split(trim($privateKeyPkcs8B64), 64, "\n")
            ."-----END PRIVATE KEY-----\n";

        $key     = openssl_pkey_get_private($pem);
        if (false === $key) {
            throw new EnvelopeException('Invalid PKCS#8 private key');
        }

        $details = openssl_pkey_get_details($key);
        if (false === $details || OPENSSL_KEYTYPE_EC !== ($details['type'] ?? -1)) {
            throw new EnvelopeException('open-banking.io private key is not an EC key');
        }

        return new self($key);
    }

    /**
     * Decrypts a base64 envelope and parses its JSON payload. A null/empty input yields null.
     *
     * @return null|array<string, mixed>
     */
    public function decryptToArray(?string $envelopeB64): ?array
    {
        if (null === $envelopeB64 || '' === $envelopeB64) {
            return null;
        }

        $raw       = base64_decode($envelopeB64, true);
        if (false === $raw) {
            throw new EnvelopeException('Invalid base64 envelope');
        }

        $plaintext = $this->decrypt($raw);

        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Decrypts the raw bytes of a zero-knowledge envelope.
     */
    public function decrypt(string $envelope): string
    {
        if (strlen($envelope) < self::HEADER_LEN || self::VERSION !== ord($envelope[0])) {
            throw new EnvelopeException('Invalid or unsupported envelope');
        }

        $ephPubRaw  = substr($envelope, 1, self::POINT_LEN);
        $nonce      = substr($envelope, 1 + self::POINT_LEN, self::NONCE_LEN);
        $tag        = substr($envelope, 1 + self::POINT_LEN + self::NONCE_LEN, self::TAG_LEN);
        $ciphertext = substr($envelope, self::HEADER_LEN);

        $ephPub     = self::publicKeyFromRawPoint($ephPubRaw);

        $shared     = openssl_pkey_derive($ephPub, $this->privateKey);
        if (false === $shared) {
            throw new EnvelopeException('ECDH key agreement failed');
        }

        $key        = hash_hkdf('sha256', $shared, 32, self::HKDF_INFO, str_repeat("\x00", 32));

        $plaintext  = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '');
        if (false === $plaintext) {
            throw new EnvelopeException('AES-256-GCM decryption failed (wrong key or tampered data)');
        }

        return $plaintext;
    }

    /**
     * Builds an EC public key from a raw 65-byte (0x04 || X || Y) P-256 point.
     */
    private static function publicKeyFromRawPoint(string $rawPoint): OpenSSLAsymmetricKey
    {
        if (self::POINT_LEN !== strlen($rawPoint) || 0x04 !== ord($rawPoint[0])) {
            throw new EnvelopeException('Invalid ephemeral public key point');
        }

        $der = hex2bin(self::SPKI_PREFIX_HEX).$rawPoint;
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode((string) $der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if (false === $key) {
            throw new EnvelopeException('Invalid ephemeral public key');
        }

        return $key;
    }
}
