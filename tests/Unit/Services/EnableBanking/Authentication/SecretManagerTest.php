<?php

/*
 * SecretManagerTest.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace Tests\Unit\Services\EnableBanking\Authentication;

use App\Services\EnableBanking\Authentication\SecretManager;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\EnableBanking\Authentication\SecretManager
 */
final class SecretManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/eb-secret-manager-'.bin2hex(random_bytes(8));
        mkdir($this->tempDir);
        session()->forget(SecretManager::EB_PRIVATE_KEY);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tempDir.'/*') as $file) {
            if (is_file((string) $file)) {
                unlink((string) $file);
            }
            if (is_dir((string) $file)) {
                rmdir((string) $file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /**
     * A misconfigured Docker/Kubernetes volume mount can turn the private key path into a
     * directory. Reading it must not blow up, see issue #12493.
     */
    public function testPrivateKeyPathIsDirectory(): void
    {
        $directory = $this->tempDir.'/key.pem';
        mkdir($directory);
        config(['eb.private_key' => $directory]);

        self::assertSame('', SecretManager::getPrivateKey());
        self::assertFalse(SecretManager::hasPrivateKeyAvailable());
    }

    /**
     * A path that exists but cannot be read must not be mistaken for the key itself.
     */
    public function testPrivateKeyFileIsNotReadable(): void
    {
        $file = $this->tempDir.'/unreadable.pem';
        file_put_contents($file, "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----");
        chmod($file, 0000);
        if (is_readable($file)) {
            // running as root, permissions are not enforced.
            self::markTestSkipped('Cannot make a file unreadable as this user.');
        }
        config(['eb.private_key' => $file]);

        self::assertSame('', SecretManager::getPrivateKey());
    }

    public function testPrivateKeyIsPemFile(): void
    {
        $pem  = sprintf("-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----", base64_encode('not-a-real-key'));
        $file = $this->tempDir.'/key.pem';
        file_put_contents($file, $pem);
        config(['eb.private_key' => $file]);

        self::assertSame($pem, SecretManager::getPrivateKey());
    }

    public function testPrivateKeyIsBase64File(): void
    {
        $base64 = base64_encode(str_repeat('this-is-not-a-real-key', 10));
        $file   = $this->tempDir.'/key.b64';
        file_put_contents($file, $base64);
        config(['eb.private_key' => $file]);

        $expected = sprintf("-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----", implode("\n", str_split($base64, 64)));
        self::assertSame($expected, SecretManager::getPrivateKey());
    }

    /**
     * Almost every editor and every `echo` leaves a trailing newline behind. That newline must not
     * stop the key from being recognised as base64.
     */
    public function testPrivateKeyIsBase64FileWithTrailingNewline(): void
    {
        $base64 = base64_encode(str_repeat('this-is-not-a-real-key', 10));
        $file   = $this->tempDir.'/key.b64';
        file_put_contents($file, $base64."\n");
        config(['eb.private_key' => $file]);

        $expected = sprintf("-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----", implode("\n", str_split($base64, 64)));
        self::assertSame($expected, SecretManager::getPrivateKey());
    }

    public function testPrivateKeyIsPemFileWithTrailingNewline(): void
    {
        $pem  = sprintf("-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----", base64_encode('not-a-real-key'));
        $file = $this->tempDir.'/key.pem';
        file_put_contents($file, $pem."\n");
        config(['eb.private_key' => $file]);

        self::assertSame($pem, SecretManager::getPrivateKey());
    }

    public function testPrivateKeyIsInlinePem(): void
    {
        $pem = sprintf("-----BEGIN PRIVATE KEY-----\n%s\n-----END PRIVATE KEY-----", base64_encode('not-a-real-key'));
        config(['eb.private_key' => $pem]);

        self::assertSame($pem, SecretManager::getPrivateKey());
    }

    public function testPrivateKeyIsEmpty(): void
    {
        config(['eb.private_key' => '']);

        self::assertSame('', SecretManager::getPrivateKey());
        self::assertFalse(SecretManager::hasPrivateKeyAvailable());
    }

    public function testSessionPrivateKeyWins(): void
    {
        config(['eb.private_key' => $this->tempDir]);
        SecretManager::savePrivateKey('key-from-session');

        self::assertSame('key-from-session', SecretManager::getPrivateKey());
    }
}
