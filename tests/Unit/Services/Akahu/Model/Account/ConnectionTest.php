<?php

/*
 * ConnectionTest.php
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

use App\Services\Akahu\Model\Account\Connection;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConnectionTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json         = '{
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "my bank",
            "logo": "https://my-bank.com/logos/logo1",
            "connection_type": "official"
        }';

        $original     = Connection::fromJson(json_decode($json, true));

        $serialized   = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Connection::fromArray(json_decode($serialized, true));

        $this->assertSame($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json       = '{
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "my bank",
            "logo": "https://my-bank.com/logos/logo1",
            "connection_type": "official"
        }';

        $connection = Connection::fromJson(json_decode($json, true));

        $this->assertSame($connection->getName(), 'my bank');
        $this->assertSame($connection->getLogo(), 'https://my-bank.com/logos/logo1');
        $this->assertSame($connection->getConnectionType(), 'official');
    }

    public function testParseJson1(): void
    {
        $json       = '{
          "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "name": "Demo Bank",
          "logo": "https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "connection_type": "official"
        }';

        $connection = Connection::fromJson(json_decode($json, true));

        $this->assertSame($connection->getName(), 'Demo Bank');
        $this->assertSame($connection->getLogo(), 'https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertSame($connection->getConnectionType(), 'official');
    }
}
