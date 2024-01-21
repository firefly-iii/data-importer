<?php
/*
 * AmountTest.php
 * Copyright (c) 2024 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

namespace Tests\Unit\Services\CSV\Converter;

use App\Services\CSV\Converter\Amount;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AmountTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function testBasicTest(): void
    {
        $amount = new Amount();

        self::assertSame('0', $amount->convert('0'));
        self::assertSame('0.0', $amount->convert('0.0'));
        self::assertSame('0.1', $amount->convert('0.1'));
        self::assertSame('0.1', $amount->convert(0.1));
        self::assertSame('1', $amount->convert(1));
        self::assertSame('1', $amount->convert('1'));
        self::assertSame('1.0', $amount->convert('1.0'));

        self::assertSame('1000.000000000000', $amount->convert('1000,-'));
        self::assertSame('1000.00', $amount->convert('1000,00'));
        self::assertSame('1000.00', $amount->convert('1.000,00'));
        self::assertSame('1000', $amount->convert('1.000,'));
        self::assertSame('1000', $amount->convert('1.000'));
        self::assertSame('1.00', $amount->convert('1.00'));
    }
}
