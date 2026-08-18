<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Akahu\Model\Transaction\PersonalFinanceGroup;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PersonalFinanceGroupTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json = '{
          "_id": "group_clasr0ysw0011hk4m6hlk9fq0",
          "name": "Lifestyle"
        }';

        $pfg  = PersonalFinanceGroup::fromJson(json_decode($json, true));

        $this->assertSame($pfg->getAkahuId(), 'group_clasr0ysw0011hk4m6hlk9fq0');
        $this->assertSame($pfg->getName(), 'Lifestyle');
    }
}
