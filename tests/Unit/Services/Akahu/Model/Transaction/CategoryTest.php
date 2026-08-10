<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Akahu\Model\Transaction\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Override;
use Tests\TestCase;

final class CategoryTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json = '{
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

