<?php

/*
 * CategoryTest.php
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

use App\Services\Akahu\Model\Transaction\Category;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CategoryTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json     = '{
          "_id": "nzfcc_ckouvvyxm004c08mlexbea79o",
          "name": "Taxi, rideshare, and on-demand transport services",
          "groups": {
            "personal_finance": {
              "_id": "group_clasr0ysw000whk4m577xhmf3",
              "name": "Transport"
            }
          }
        }';

        $category = Category::fromJson(json_decode($json, true));

        $this->assertSame($category->getNzfccId(), 'nzfcc_ckouvvyxm004c08mlexbea79o');
        $this->assertSame($category->getName(), 'Taxi, rideshare, and on-demand transport services');
        $this->assertSame($category->getPersonalFinanceGroup()?->getAkahuId(), 'group_clasr0ysw000whk4m577xhmf3');
        $this->assertSame($category->getPersonalFinanceGroup()?->getName(), 'Transport');
    }
}
