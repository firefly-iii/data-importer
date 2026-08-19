<?php


/*
 * BalanceTest.php
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

namespace Tests\Unit\Services\Akahu\Model\Account;

use App\Services\Akahu\Model\Account\Balance;
use BcMath\Number;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BalanceTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json         = '{
          "current": 100000,
          "available": 100000,
          "limit": 10000,
          "overdrawn": false,
          "currency": "NZD"
        }';

        $original     = Balance::fromJson(json_decode($json, true));

        $serialized   = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Balance::fromArray(json_decode($serialized, true));

        $this->assertSame($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json    = '{
          "current": 100000,
          "available": 100000,
          "limit": 10000,
          "overdrawn": false,
          "currency": "NZD"
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertSame($balance->getCurrent(), new Number('100000'));
        $this->assertSame($balance->getAvailable(), new Number('100000'));
        $this->assertSame($balance->getLimit(), new Number('10000'));
        $this->assertFalse($balance->getOverdrawn());
        $this->assertSame($balance->getCurrency(), 'NZD');
    }

    public function testParseJson1(): void
    {
        $json    = '{
          "currency": "NZD",
          "current": 100000,
          "available": 100000,
          "limit": 10000
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertSame($balance->getCurrent(), new Number('100000'));
        $this->assertSame($balance->getAvailable(), new Number('100000'));
        $this->assertSame($balance->getLimit(), new Number('10000'));
        $this->assertNull($balance->getOverdrawn());
        $this->assertSame($balance->getCurrency(), 'NZD');
    }

    public function testParseJson2(): void
    {
        $json    = '{
          "currency": "NZD",
          "current": -5500,
          "available": 4500,
          "limit": 10000
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertSame($balance->getCurrent(), new Number('-5500'));
        $this->assertSame($balance->getAvailable(), new Number('4500'));
        $this->assertSame($balance->getLimit(), new Number('10000'));
        $this->assertNull($balance->getOverdrawn());
        $this->assertSame($balance->getCurrency(), 'NZD');
    }
}
