<?php

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
