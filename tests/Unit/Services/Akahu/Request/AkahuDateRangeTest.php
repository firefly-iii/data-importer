<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Request;

use App\Services\Shared\Configuration\Configuration;



use App\Services\Akahu\Request\AkahuDateRange;
use Illuminate\Support\Facades\Config;



use Tests\TestCase;


// Testing date range behaviour:
// curl \
//   -H "Authorization: Bearer user_token_aaaaaaaaaaaaaaaaaaaaaaaaa" \
//   -H "X-Akahu-Id: app_token_aaaaaaaaaaaaaaaaaaaaaaaaa" \
//   'https://api.akahu.io/v1/transactions?start=<start-iso>&end=<end-iso>' -v \
//   | jq '.items.[] | {description:.description, date:.date, amount:.amount}'


final class AkahuDateRangeTest extends TestCase
{
    public function testOneDay(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config = Configuration::fromArray([
            'date_not_before' => '2026-08-13',
            'date_not_after' => '2026-08-13',
        ]);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-12T11:59:59.999000Z');
        $this->assertSame($dateRange->endDate()->toIsoString(), '2026-08-13T12:00:00.000000Z');
    }

    public function testMultipleDays(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config = Configuration::fromArray([
            'date_not_before' => '2026-08-10',
            'date_not_after' => '2026-08-14',
        ]);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-09T11:59:59.999000Z');
        $this->assertSame($dateRange->endDate()->toIsoString(), '2026-08-14T12:00:00.000000Z');
    }

    public function testOneSided(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config = Configuration::fromArray([
            'date_not_before' => '2026-08-13',
            'date_not_after' => '',
        ]);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-12T11:59:59.999000Z');
        $this->assertNull($dateRange->endDate()?->toIsoString());
    }
}
