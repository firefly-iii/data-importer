<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Akahu\Model\Transaction\Merchant;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MerchantTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json     = '{
          "_id": "merchant_cksxp1au3001g09mp3ilt01tz",
          "name": "The Wholemeal Cafe",
          "website": "https://wholemealcafe.co.nz/"
        }';

        $merchant = Merchant::fromJson(json_decode($json, true));

        $this->assertSame($merchant->getAkahuId(), 'merchant_cksxp1au3001g09mp3ilt01tz');
        $this->assertSame($merchant->getName(), 'The Wholemeal Cafe');
        $this->assertSame($merchant->getWebsite(), 'https://wholemealcafe.co.nz/');
    }
}
