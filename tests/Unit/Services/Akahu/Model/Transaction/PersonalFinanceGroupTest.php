<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;


use App\Services\Akahu\Model\Transaction\PersonalFinanceGroup;



use Tests\TestCase;

final class PersonalFinanceGroupTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json = '{
          "_id": "group_clasr0ysw0011hk4m6hlk9fq0",
          "name": "Lifestyle"
        }';

        $pfg = PersonalFinanceGroup::fromJson(json_decode($json, true));

        $this->assertEquals($pfg->getAkahuId(), 'group_clasr0ysw0011hk4m6hlk9fq0');
        $this->assertEquals($pfg->getName(), 'Lifestyle');
    }
}

