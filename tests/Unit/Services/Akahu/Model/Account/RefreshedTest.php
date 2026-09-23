<?php

/*
 * RefreshedTest.php
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

use App\Services\Akahu\Model\Account\Refreshed;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RefreshedTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json         = '{
          "balance": "2026-08-05T03:22:25.542Z",
          "meta": "2026-08-04T03:22:25.542Z",
          "transactions": "2026-08-03T03:22:29.023Z",
          "party": "2026-08-02T10:22:29.023Z"
        }';

        $original     = Refreshed::fromJson(json_decode($json, true));

        $serialized   = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Refreshed::fromArray(json_decode($serialized, true));

        $this->assertSame($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json      = '{
          "balance": "2026-08-05T03:22:25.542Z",
          "meta": "2026-08-04T03:22:25.542Z",
          "transactions": "2026-08-03T03:22:29.023Z",
          "party": "2026-08-02T10:22:29.023Z"
        }';

        $refreshed = Refreshed::fromJson(json_decode($json, true));

        $this->assertSame($refreshed->getBalance(), Carbon::parse('2026-08-05T03:22:25.542Z'));
        $this->assertSame($refreshed->getMeta(), Carbon::parse('2026-08-04T03:22:25.542Z'));
        $this->assertSame($refreshed->getTransactions(), Carbon::parse('2026-08-03T03:22:29.023Z'));
        $this->assertSame($refreshed->getParty(), Carbon::parse('2026-08-02T10:22:29.023Z'));
    }
}
