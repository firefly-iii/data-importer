<?php

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
