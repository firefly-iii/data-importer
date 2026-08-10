<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Account;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Akahu\Model\Account\Balance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Override;
use Tests\TestCase;
use BcMath\Number;

final class BalanceTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json = '{
          "current": 100000,
          "available": 100000,
          "limit": 10000,
          "overdrawn": false,
          "currency": "NZD"
        }';

        $original = Balance::fromJson(json_decode($json, true));

        $serialized = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Balance::fromArray(json_decode($serialized, true));

        $this->assertEquals($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json = '{
          "current": 100000,
          "available": 100000,
          "limit": 10000,
          "overdrawn": false,
          "currency": "NZD"
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertEquals($balance->getCurrent(), new Number("100000"));
        $this->assertEquals($balance->getAvailable(), new Number("100000"));
        $this->assertEquals($balance->getLimit(), new Number("10000"));
        $this->assertEquals($balance->getOverdrawn(), false);
        $this->assertEquals($balance->getCurrency(), 'NZD');
    }

    public function testParseJson1(): void
    {
        $json = '{
          "currency": "NZD",
          "current": 100000,
          "available": 100000,
          "limit": 10000
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertEquals($balance->getCurrent(), new Number("100000"));
        $this->assertEquals($balance->getAvailable(), new Number("100000"));
        $this->assertEquals($balance->getLimit(), new Number("10000"));
        $this->assertEquals($balance->getOverdrawn(), null);
        $this->assertEquals($balance->getCurrency(), 'NZD');
    }

    public function testParseJson2(): void
    {
        $json = '{
          "currency": "NZD",
          "current": -5500,
          "available": 4500,
          "limit": 10000
        }';

        $balance = Balance::fromJson(json_decode($json, true));

        $this->assertEquals($balance->getCurrent(), new Number("-5500"));
        $this->assertEquals($balance->getAvailable(), new Number("4500"));
        $this->assertEquals($balance->getLimit(), new Number("10000"));
        $this->assertEquals($balance->getOverdrawn(), null);
        $this->assertEquals($balance->getCurrency(), 'NZD');
    }
}
