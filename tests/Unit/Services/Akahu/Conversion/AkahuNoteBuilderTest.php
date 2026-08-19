<?php


/*
 * AkahuNoteBuilderTest.php
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

namespace Tests\Unit\Services\Akahu\Conversion;

use App\Services\Akahu\Conversion\AkahuNoteBuilder;
use App\Services\Akahu\Model\Transaction\Transaction;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AkahuNoteBuilderTest extends TestCase
{
    public function testBuildNoteFull(): void
    {
        $json        = '{
          "_id": "trans_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_account": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_user": "user_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_connection": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "created_at": "2003-07-30T22:48:42+00:00",
          "updated_at": "1998-11-22T11:41:08+00:00",
          "date": "2010-12-07T10:02:08+00:00",
          "description": "Quic Broadba Card number: aaaa **** **** aaaa",
          "amount": -71,
          "balance": 100,
          "type": "EFTPOS",
          "hash": "acc_aaaaaaaaaaaaaaaaaaaaaaaar-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
          "meta": {
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
          },
          "merchant": {
            "_id": "merchant_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "Quic Broadband",
            "website": "https://www.quic.nz"
          },
          "category": {
            "_id": "nzfcc_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "Internet services",
            "groups": {
              "personal_finance": {
                "_id": "group_aaaaaaaaaaaaaaaaaaaaaaaaa",
                "name": "Utilities"
              }
            }
          }
        }';

        $transaction = Transaction::fromJson(json_decode($json, true));

        $noteBuilder = new AkahuNoteBuilder($transaction);

        $this->assertSame($noteBuilder->build(), '#### Transaction Metadata
 - Particulars: my particulars
 - Code: my code
 - Reference: my ref
 - Other account: 00-0000-0000000-00

#### Eftpos Infomation
 - Card Suffix: 1234
 - Logo: https://cdn.akahu.nz/logos/merchants/merchant_aaaaaaaaaaaaaaaaaaaaaaaaa

#### Merchant Infomation
 - Name: Quic Broadband
 - Website: https://www.quic.nz

#### Akahu NZFCC classification (https://nzfcc.org/)
 - Name: Internet services
 - Personal finance category: Utilities

');
    }

    public function testBuildNotePartial(): void
    {
        $json        = '{
          "_id": "trans_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_account": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "date": "2010-12-07T10:02:08+00:00",
          "description": "Quic Broadba Card number: aaaa **** **** aaaa",
          "amount": -71,
          "type": "EFTPOS",
          "meta": {
            "particulars": "my particulars",
            "other_account": "00-0000-0000000-00"
          }
        }';

        $transaction = Transaction::fromJson(json_decode($json, true));

        $noteBuilder = new AkahuNoteBuilder($transaction);

        $this->assertSame($noteBuilder->build(), '#### Transaction Metadata
 - Particulars: my particulars
 - Other account: 00-0000-0000000-00

');
    }
}
