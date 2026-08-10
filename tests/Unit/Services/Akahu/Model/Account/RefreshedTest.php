<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Account;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Akahu\Model\Account\Refreshed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Override;
use Tests\TestCase;

final class RefreshedTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json = '{
          "balance": "2026-08-05T03:22:25.542Z",
          "meta": "2026-08-04T03:22:25.542Z",
          "transactions": "2026-08-03T03:22:29.023Z",
          "party": "2026-08-02T10:22:29.023Z"
        }';

        $original = Refreshed::fromJson(json_decode($json, true));

        $serialized = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Refreshed::fromArray(json_decode($serialized, true));

        $this->assertEquals($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json = '{
          "balance": "2026-08-05T03:22:25.542Z",
          "meta": "2026-08-04T03:22:25.542Z",
          "transactions": "2026-08-03T03:22:29.023Z",
          "party": "2026-08-02T10:22:29.023Z"
        }';

        $refreshed = Refreshed::fromJson(json_decode($json, true));

        $this->assertEquals($refreshed->getBalance(), Carbon::parse('2026-08-05T03:22:25.542Z'));
        $this->assertEquals($refreshed->getMeta(), Carbon::parse('2026-08-04T03:22:25.542Z'));
        $this->assertEquals($refreshed->getTransactions(), Carbon::parse('2026-08-03T03:22:29.023Z'));
        $this->assertEquals($refreshed->getParty(), Carbon::parse('2026-08-02T10:22:29.023Z'));
    }
}
