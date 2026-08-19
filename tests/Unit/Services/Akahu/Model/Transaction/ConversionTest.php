<?php


/*
 * ConversionTest.php
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

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Akahu\Model\Transaction\Conversion;
use BcMath\Number;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConversionTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json       = '{
          "fee": 0.11,
          "rate": 0.12,
          "amount": 0.13,
          "currency": "AUD"
        }';

        $conversion = Conversion::fromJson(json_decode($json, true));

        $this->assertSame($conversion->getAmount(), new Number('0.13'));
        $this->assertSame($conversion->getCurrency(), 'AUD');
        $this->assertSame($conversion->getRate(), new Number('0.12'));
        $this->assertSame($conversion->getFee(), new Number('0.11'));
    }
}
