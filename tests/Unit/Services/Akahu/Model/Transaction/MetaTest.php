<?php


/*
 * MetaTest.php
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

use App\Services\Akahu\Model\Transaction\Meta;
use BcMath\Number;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MetaTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json = '{
            "particulars": "my particulars",
            "code": "my code",
            "reference": "my ref",

            "other_account": "00-0000-0000000-00",

            "conversion": {
              "amount": 2.15,
              "currency": "GBP",
              "rate": 0.49
            },

            "card_suffix": "1234",
            "logo": "https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertSame($meta->getParticulars(), 'my particulars');
        $this->assertSame($meta->getCode(), 'my code');
        $this->assertSame($meta->getReference(), 'my ref');
        $this->assertSame($meta->getOtherAccount(), '00-0000-0000000-00');
        $this->assertSame($meta->getConversion()?->getAmount(), new Number('2.15'));
        $this->assertSame($meta->getConversion()?->getCurrency(), 'GBP');
        $this->assertSame($meta->getConversion()?->getRate(), new Number('0.49'));
        $this->assertSame($meta->getCardSuffix(), '1234');
        $this->assertSame($meta->getLogo(), 'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa');
    }

    public function testParseJson1(): void
    {
        $json = '{
            "card_suffix": "1234",
            "logo": "https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertNull($meta->getParticulars());
        $this->assertNull($meta->getCode());
        $this->assertNull($meta->getReference());
        $this->assertNull($meta->getOtherAccount());
        $this->assertNull($meta->getConversion()?->getAmount());
        $this->assertNull($meta->getConversion()?->getCurrency());
        $this->assertNull($meta->getConversion()?->getRate());
        $this->assertNull($meta->getConversion()?->getFee());
        $this->assertSame($meta->getCardSuffix(), '1234');
        $this->assertSame($meta->getLogo(), 'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa');
    }

    public function testParseJson2(): void
    {
        $json = '{
          "conversion": {
            "fee": 0.22,
            "rate": 0.44,
            "amount": 12.34,
            "currency": "AUD"
          },
          "card_suffix": "1234",
          "logo": "https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertNull($meta->getParticulars());
        $this->assertNull($meta->getCode());
        $this->assertNull($meta->getReference());
        $this->assertNull($meta->getOtherAccount());
        $this->assertSame($meta->getConversion()?->getAmount(), new Number('12.34'));
        $this->assertSame($meta->getConversion()?->getCurrency(), 'AUD');
        $this->assertSame($meta->getConversion()?->getRate(), new Number('0.44'));
        $this->assertSame($meta->getConversion()?->getFee(), new Number('0.22'));
        $this->assertSame($meta->getCardSuffix(), '1234');
        $this->assertSame($meta->getLogo(), 'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa');
    }

    public function testParseJson3(): void
    {
        $json = '{
          "conversion": {
            "fee": 0.11,
            "rate": 0.99,
            "amount": 1.11,
            "currency": "NZD"
          },
          "card_suffix": "1234"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertNull($meta->getParticulars());
        $this->assertNull($meta->getCode());
        $this->assertNull($meta->getReference());
        $this->assertNull($meta->getOtherAccount());
        $this->assertSame($meta->getConversion()?->getAmount(), new Number('1.11'));
        $this->assertSame($meta->getConversion()?->getCurrency(), 'NZD');
        $this->assertSame($meta->getConversion()?->getRate(), new Number('0.99'));
        $this->assertSame($meta->getConversion()?->getFee(), new Number('0.11'));
        $this->assertSame($meta->getCardSuffix(), '1234');
        $this->assertNull($meta->getLogo());
    }

    public function testParseJson4(): void
    {
        $json = '{
          "particulars": "Meow",
          "code": "woeM",
          "reference": "MEOW",
          "other_account": "00-1111-2222222-33"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertSame($meta->getParticulars(), 'Meow');
        $this->assertSame($meta->getCode(), 'woeM');
        $this->assertSame($meta->getReference(), 'MEOW');
        $this->assertSame($meta->getOtherAccount(), '00-1111-2222222-33');
        $this->assertNull($meta->getConversion()?->getAmount());
        $this->assertNull($meta->getConversion()?->getCurrency());
        $this->assertNull($meta->getConversion()?->getRate());
        $this->assertNull($meta->getConversion()?->getFee());
        $this->assertNull($meta->getCardSuffix());
        $this->assertNull($meta->getLogo());
    }

    // This can happen on transactions with type INTEREST
    public function testParseJson5(): void
    {
        $json = '{}';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertNull($meta->getParticulars());
        $this->assertNull($meta->getCode());
        $this->assertNull($meta->getReference());
        $this->assertNull($meta->getOtherAccount());
        $this->assertNull($meta->getConversion()?->getAmount());
        $this->assertNull($meta->getConversion()?->getCurrency());
        $this->assertNull($meta->getConversion()?->getRate());
        $this->assertNull($meta->getConversion()?->getFee());
        $this->assertNull($meta->getCardSuffix());
        $this->assertNull($meta->getLogo());
    }

    public function testParseJson6(): void
    {
        $json = '{
          "other_account": "11-22-3333333-44"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertNull($meta->getParticulars());
        $this->assertNull($meta->getCode());
        $this->assertNull($meta->getReference());
        $this->assertSame($meta->getOtherAccount(), '11-22-3333333-44');
        $this->assertNull($meta->getConversion()?->getAmount());
        $this->assertNull($meta->getConversion()?->getCurrency());
        $this->assertNull($meta->getConversion()?->getRate());
        $this->assertNull($meta->getConversion()?->getFee());
        $this->assertNull($meta->getCardSuffix());
        $this->assertNull($meta->getLogo());
    }
}
