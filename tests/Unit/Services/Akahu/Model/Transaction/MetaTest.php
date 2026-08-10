<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Akahu\Model\Transaction\Meta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Override;
use Tests\TestCase;
use BcMath\Number;

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

        $this->assertEquals($meta->getParticulars(), 'my particulars');
        $this->assertEquals($meta->getCode(), 'my code');
        $this->assertEquals($meta->getReference(), 'my ref');
        $this->assertEquals($meta->getOtherAccount(), '00-0000-0000000-00');
        $this->assertEquals($meta->getConversion()?->getAmount(), new Number('2.15'));
        $this->assertEquals($meta->getConversion()?->getCurrency(), 'GBP');
        $this->assertEquals($meta->getConversion()?->getRate(), new Number('0.49'));
        $this->assertEquals($meta->getCardSuffix(), '1234');
        $this->assertEquals(
            $meta->getLogo(),
            'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa'
        );
    }

    public function testParseJson1(): void
    {
        $json = '{
            "card_suffix": "1234",
            "logo": "https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertEquals($meta->getParticulars(), null);
        $this->assertEquals($meta->getCode(), null);
        $this->assertEquals($meta->getReference(), null);
        $this->assertEquals($meta->getOtherAccount(), null);
        $this->assertEquals($meta->getConversion()?->getAmount(), null);
        $this->assertEquals($meta->getConversion()?->getCurrency(), null);
        $this->assertEquals($meta->getConversion()?->getRate(), null);
        $this->assertEquals($meta->getConversion()?->getFee(), null);
        $this->assertEquals($meta->getCardSuffix(), '1234');
        $this->assertEquals(
            $meta->getLogo(),
            'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa'
        );
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

        $this->assertEquals($meta->getParticulars(), null);
        $this->assertEquals($meta->getCode(), null);
        $this->assertEquals($meta->getReference(), null);
        $this->assertEquals($meta->getOtherAccount(), null);
        $this->assertEquals($meta->getConversion()?->getAmount(), new Number('12.34'));
        $this->assertEquals($meta->getConversion()?->getCurrency(), 'AUD');
        $this->assertEquals($meta->getConversion()?->getRate(), new Number('0.44'));
        $this->assertEquals($meta->getConversion()?->getFee(), new Number('0.22'));
        $this->assertEquals($meta->getCardSuffix(), '1234');
        $this->assertEquals(
            $meta->getLogo(),
            'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa'
        );
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

        $this->assertEquals($meta->getParticulars(), null);
        $this->assertEquals($meta->getCode(), null);
        $this->assertEquals($meta->getReference(), null);
        $this->assertEquals($meta->getOtherAccount(), null);
        $this->assertEquals($meta->getConversion()?->getAmount(), new Number('1.11'));
        $this->assertEquals($meta->getConversion()?->getCurrency(), 'NZD');
        $this->assertEquals($meta->getConversion()?->getRate(), new Number('0.99'));
        $this->assertEquals($meta->getConversion()?->getFee(), new Number('0.11'));
        $this->assertEquals($meta->getCardSuffix(), '1234');
        $this->assertEquals($meta->getLogo(), null);
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

        $this->assertEquals($meta->getParticulars(), 'Meow');
        $this->assertEquals($meta->getCode(), 'woeM');
        $this->assertEquals($meta->getReference(), 'MEOW');
        $this->assertEquals($meta->getOtherAccount(), '00-1111-2222222-33');
        $this->assertEquals($meta->getConversion()?->getAmount(), null);
        $this->assertEquals($meta->getConversion()?->getCurrency(), null);
        $this->assertEquals($meta->getConversion()?->getRate(), null);
        $this->assertEquals($meta->getConversion()?->getFee(), null);
        $this->assertEquals($meta->getCardSuffix(), null);
        $this->assertEquals($meta->getLogo(), null);
    }

    // This can happen on transactions with type INTEREST
    public function testParseJson5(): void
    {
        $json = '{}';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertEquals($meta->getParticulars(), null);
        $this->assertEquals($meta->getCode(), null);
        $this->assertEquals($meta->getReference(), null);
        $this->assertEquals($meta->getOtherAccount(), null);
        $this->assertEquals($meta->getConversion()?->getAmount(), null);
        $this->assertEquals($meta->getConversion()?->getCurrency(), null);
        $this->assertEquals($meta->getConversion()?->getRate(), null);
        $this->assertEquals($meta->getConversion()?->getFee(), null);
        $this->assertEquals($meta->getCardSuffix(), null);
        $this->assertEquals($meta->getLogo(), null);
    }

    public function testParseJson6(): void
    {
        $json = '{
          "other_account": "11-22-3333333-44"
        }';

        $meta = Meta::fromJson(json_decode($json, true));

        $this->assertEquals($meta->getParticulars(), null);
        $this->assertEquals($meta->getCode(), null);
        $this->assertEquals($meta->getReference(), null);
        $this->assertEquals($meta->getOtherAccount(), '11-22-3333333-44');
        $this->assertEquals($meta->getConversion()?->getAmount(), null);
        $this->assertEquals($meta->getConversion()?->getCurrency(), null);
        $this->assertEquals($meta->getConversion()?->getRate(), null);
        $this->assertEquals($meta->getConversion()?->getFee(), null);
        $this->assertEquals($meta->getCardSuffix(), null);
        $this->assertEquals($meta->getLogo(), null);
    }
}
