<?php


/*
 * AkahuDateRangeTest.php
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

namespace Tests\Unit\Services\Akahu\Request;

use App\Services\Akahu\Request\AkahuDateRange;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

// Testing date range behaviour:
// curl \
//   -H "Authorization: Bearer user_token_aaaaaaaaaaaaaaaaaaaaaaaaa" \
//   -H "X-Akahu-Id: app_token_aaaaaaaaaaaaaaaaaaaaaaaaa" \
//   'https://api.akahu.io/v1/transactions?start=<start-iso>&end=<end-iso>' -v \
//   | jq '.items.[] | {description:.description, date:.date, amount:.amount}'

/**
 * @internal
 *
 * @coversNothing
 */
final class AkahuDateRangeTest extends TestCase
{
    public function testOneDay(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config    = Configuration::fromArray(['date_not_before' => '2026-08-13', 'date_not_after' => '2026-08-13']);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-12T11:59:59.999000Z');
        $this->assertSame($dateRange->endDate()->toIsoString(), '2026-08-13T12:00:00.000000Z');
    }

    public function testMultipleDays(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config    = Configuration::fromArray(['date_not_before' => '2026-08-10', 'date_not_after' => '2026-08-14']);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-09T11:59:59.999000Z');
        $this->assertSame($dateRange->endDate()->toIsoString(), '2026-08-14T12:00:00.000000Z');
    }

    public function testOneSided(): void
    {
        Config::set('app.timezone', 'Pacific/Auckland');

        $config    = Configuration::fromArray(['date_not_before' => '2026-08-13', 'date_not_after' => '']);

        $dateRange = new AkahuDateRange($config);

        $this->assertSame($dateRange->startDate()->toIsoString(), '2026-08-12T11:59:59.999000Z');
        $this->assertNull($dateRange->endDate()?->toIsoString());
    }
}
