<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Transactions;

use App\Services\Shared\Configuration\Configuration;
use App\Services\Akahu\Model\Transaction\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Override;
use Tests\TestCase;
use BcMath\Number;

final class TransactionTest extends TestCase
{
    public function testParseJsonFull(): void
    {
        $json = '{
          "_id": "trans_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_account": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_user": "user_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_connection": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "created_at": "2026-07-25T03:38:50.300Z",
          "updated_at": "2026-07-25T03:38:51.536Z",
          "date": "2026-06-29T11:39:52.000Z",
          "description": "blah blah blah",
          "amount": -100,
          "balance": 1234567,
          "type": "EFTPOS",
          "hash": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
          "meta": {
            "particulars": "Payment",
            "code": "ref:1234",
            "reference": "12345678",
            "other_account": "11-2222-3333333-44",
            "conversion": {
              "fee": 0.11,
              "rate": 0.22,
              "amount": 0.33,
              "currency": "USD"
            },
            "card_suffix": "1234",
            "logo": "https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa"
          },
          "merchant": {
            "_id": "merchant_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "Business",
            "website": "https://business.com",
            "nzbn": "1111111111111"
          },
          "category": {
            "_id": "nzfcc_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "NZFCC Category name",
            "groups": {
              "personal_finance": {
                "_id": "group_aaaaaaaaaaaaaaaaaaaaaaaaa",
                "name": "Professional Services"
              }
            }
          }
        }';

        $transaction = Transaction::fromJson(json_decode($json, true));

        $this->assertEquals($transaction->getAkahuId(), 'trans_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($transaction->getAkahuAccountId(), 'acc_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($transaction->getCreatedAt(), Carbon::parse('2026-07-25T03:38:50.300Z'));
        $this->assertEquals($transaction->getDate(), Carbon::parse('2026-06-29T11:39:52.000Z'));
        $this->assertEquals($transaction->getDescription(), 'blah blah blah');
        $this->assertEquals($transaction->getAmount(), new Number('-100'));
        $this->assertEquals($transaction->getBalance(), new Number('1234567'));
        $this->assertEquals($transaction->getType(), 'EFTPOS');

        $this->assertEquals($transaction->getCategory()?->getNzfccId(), 'nzfcc_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($transaction->getCategory()?->getName(), 'NZFCC Category name');
        $this->assertEquals($transaction->getCategory()?->getPersonalFinanceGroup()?->getAkahuId(), 'group_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($transaction->getCategory()?->getPersonalFinanceGroup()?->getName(), 'Professional Services');

        $this->assertEquals($transaction->getMerchant()?->getAkahuId(), 'merchant_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($transaction->getMerchant()?->getName(), 'Business');
        $this->assertEquals($transaction->getMerchant()?->getWebsite(), 'https://business.com');
        $this->assertEquals($transaction->getMerchant()?->getNZBN(), '1111111111111');

        $this->assertEquals($transaction->getMeta()?->getParticulars(), 'Payment');
        $this->assertEquals($transaction->getMeta()?->getCode(), 'ref:1234');
        $this->assertEquals($transaction->getMeta()?->getReference(), '12345678');
        $this->assertEquals($transaction->getMeta()?->getOtherAccount(), '11-2222-3333333-44');

        $this->assertEquals($transaction->getMeta()?->getConversion()?->getAmount(), new Number('0.33'));
        $this->assertEquals($transaction->getMeta()?->getConversion()?->getCurrency(), 'USD');
        $this->assertEquals($transaction->getMeta()?->getConversion()?->getRate(), new Number('0.22'));
        $this->assertEquals($transaction->getMeta()?->getConversion()?->getFee(), new Number('0.11'));

        $this->assertEquals($transaction->getMeta()?->getCardSuffix(), '1234');
        $this->assertEquals($transaction->getMeta()?->getLogo(), 'https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa');
    }
}

