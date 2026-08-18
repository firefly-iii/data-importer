<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Akahu\Model\Account;


use App\Services\Akahu\Model\Account\Account;



use Carbon\Carbon;


use Tests\TestCase;

final class AccountTest extends TestCase
{
    public function testSerializeDeserializeFull(): void
    {
        $json = '{
          "_id": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_authorisation": "authorisation_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_credentials": "creds_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "connection": {
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "my bank",
            "logo": "https://my-bank.com/logos/logo1",
            "connection_type": "official"
          },
          "name": "Business Account",
          "formatted_account": "99-9999-0000000-00",
          "status": "ACTIVE",
          "type": "CHECKING",
          "meta": {},
          "attributes": [
            "PAYMENT_FROM",
            "PAYMENT_TO"
          ],
          "balance": {
            "current": 100000,
            "available": 100000,
            "limit": 10000,
            "overdrawn": false,
            "currency": "NZD"
          },
          "refreshed": {
            "balance": "2026-08-05T03:22:25.542Z",
            "meta": "2026-08-04T03:22:25.542Z",
            "transactions": "2026-08-03T03:22:29.023Z",
            "party": "2026-08-02T10:22:29.023Z"
          },
          "attributes": [
            "PAYMENT_TO",
            "PAYMENT_FROM",
            "TRANSACTIONS"
          ]
        }';

        $original = Account::fromJson(json_decode($json, true));

        $serialized = json_encode($original->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $deserialized = Account::fromArray(json_decode($serialized, true));

        $this->assertEquals($original, $deserialized);
    }

    public function testParseJsonFull(): void
    {
        $json = '{
          "_id": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_authorisation": "authorisation_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_credentials": "creds_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "connection": {
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "my bank",
            "logo": "https://my-bank.com/logos/logo1",
            "connection_type": "official"
          },
          "name": "Business Account",
          "formatted_account": "99-9999-0000000-00",
          "status": "ACTIVE",
          "type": "CHECKING",
          "meta": {},
          "attributes": [
            "PAYMENT_FROM",
            "PAYMENT_TO"
          ],
          "balance": {
            "current": 100000,
            "available": 100000,
            "limit": 10000,
            "overdrawn": false,
            "currency": "NZD"
          },
          "refreshed": {
            "balance": "2026-08-05T03:22:25.542Z",
            "meta": "2026-08-04T03:22:25.542Z",
            "transactions": "2026-08-03T03:22:29.023Z",
            "party": "2026-08-02T10:22:29.023Z"
          },
          "attributes": [
            "PAYMENT_TO",
            "PAYMENT_FROM",
            "TRANSACTIONS"
          ]
        }';

        $account = Account::fromJson(json_decode($json, true));

        $this->assertEquals($account->getAkahuId(), 'acc_aaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertEquals($account->getConnection()?->getName(), 'my bank');
        $this->assertEquals($account->getConnection()?->getLogo(), 'https://my-bank.com/logos/logo1');
        $this->assertEquals($account->getConnection()?->getConnectionType(), 'official');

        $this->assertEquals($account->getName(), 'Business Account');
        $this->assertEquals($account->getAkahuStatus(), 'ACTIVE');
        $this->assertEquals($account->getFormattedAccount(), '99-9999-0000000-00');
        $this->assertEquals($account->getMeta(), []);

        $this->assertEquals($account->getRefreshed()?->getBalance(), Carbon::parse('2026-08-05T03:22:25.542Z'));
        $this->assertEquals($account->getRefreshed()?->getMeta(), Carbon::parse('2026-08-04T03:22:25.542Z'));
        $this->assertEquals($account->getRefreshed()?->getTransactions(), Carbon::parse('2026-08-03T03:22:29.023Z'));
        $this->assertEquals($account->getRefreshed()?->getParty(), Carbon::parse('2026-08-02T10:22:29.023Z'));

        $this->assertEquals($account->getType(), 'CHECKING');
        $this->assertEquals($account->getAttributes(), [
            Account::ATTRIBUTE_PAYMENT_TO,
            Account::ATTRIBUTE_PAYMENT_FROM,
            Account::ATTRIBUTE_TRANSACTIONS,
        ]);
    }

    public function testParseJson1(): void
    {

        $json = '{
          "_id": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_authorisation": "authorisation_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_credentials": "creds_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "connection": {
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "Demo Bank",
            "logo": "https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "connection_type": "official"
          },
          "name": "Business Account",
          "formatted_account": "99-9999-0000000-00",
          "status": "ACTIVE",
          "type": "CHECKING",
          "attributes": [
            "PAYMENT_FROM",
            "PAYMENT_TO"
          ],
          "balance": {
            "currency": "NZD",
            "current": 100000,
            "available": 100000,
            "limit": 10000
          },
          "refreshed": {
            "balance": "2026-08-05T10:15:45.863Z",
            "meta": "2026-08-05T10:15:45.863Z"
          }
        }';

        $account = Account::fromJson(json_decode($json, true));

        $this->assertEquals($account->getAkahuId(), 'acc_aaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertEquals($account->getConnection()?->getName(), 'Demo Bank');
        $this->assertEquals($account->getConnection()?->getLogo(), 'https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($account->getConnection()?->getConnectionType(), 'official');

        $this->assertEquals($account->getName(), 'Business Account');
        $this->assertEquals($account->getAkahuStatus(), 'ACTIVE');
        $this->assertEquals($account->getFormattedAccount(), '99-9999-0000000-00');
        $this->assertEquals($account->getMeta(), null);

        $this->assertEquals($account->getRefreshed()?->getBalance(), Carbon::parse('2026-08-05T10:15:45.863Z'));
        $this->assertEquals($account->getRefreshed()?->getMeta(), Carbon::parse('2026-08-05T10:15:45.863Z'));
        $this->assertEquals($account->getRefreshed()?->getTransactions(), null);
        $this->assertEquals($account->getRefreshed()?->getParty(), null);

        $this->assertEquals($account->getType(), 'CHECKING');
        $this->assertEquals($account->getAttributes(), [
            Account::ATTRIBUTE_PAYMENT_FROM,
            Account::ATTRIBUTE_PAYMENT_TO,
        ]);
    }

    public function testParseJson2(): void
    {

        $json = '{
          "_id": "acc_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_authorisation": "authorisation_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "_credentials": "creds_aaaaaaaaaaaaaaaaaaaaaaaaa",
          "connection": {
            "_id": "conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "name": "ANZ",
            "logo": "https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa",
            "connection_type": "official"
          },
          "name": "my account",
          "formatted_account": "11-0000-1111111-00",
          "status": "ACTIVE",
          "type": "CHECKING",
          "attributes": [
            "PAYMENT_TO",
            "PAYMENT_FROM",
            "TRANSACTIONS"
          ],
          "balance": {
            "currency": "NZD",
            "current": 111.99
          },
          "refreshed": {
            "balance": "2026-08-05T03:22:25.542Z",
            "meta": "2026-08-05T03:22:25.542Z",
            "transactions": "2026-08-05T03:22:29.023Z"
          }
        }';

        $account = Account::fromJson(json_decode($json, true));

        $this->assertEquals($account->getAkahuId(), 'acc_aaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertEquals($account->getConnection()?->getName(), 'ANZ');
        $this->assertEquals($account->getConnection()?->getLogo(), 'https://cdn.akahu.nz/logos/connections/conn_aaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->assertEquals($account->getConnection()?->getConnectionType(), 'official');

        $this->assertEquals($account->getName(), 'my account');
        $this->assertEquals($account->getAkahuStatus(), 'ACTIVE');
        $this->assertEquals($account->getFormattedAccount(), '11-0000-1111111-00');
        $this->assertEquals($account->getMeta(), null);

        $this->assertEquals($account->getRefreshed()?->getBalance(), Carbon::parse('2026-08-05T03:22:25.542Z'));
        $this->assertEquals($account->getRefreshed()?->getMeta(), Carbon::parse('2026-08-05T03:22:25.542Z'));
        $this->assertEquals($account->getRefreshed()?->getTransactions(), Carbon::parse('2026-08-05T03:22:29.023Z'));
        $this->assertEquals($account->getRefreshed()?->getParty(), null);

        $this->assertEquals($account->getType(), 'CHECKING');
        $this->assertEquals($account->getAttributes(), [
            Account::ATTRIBUTE_PAYMENT_TO,
            Account::ATTRIBUTE_PAYMENT_FROM,
            Account::ATTRIBUTE_TRANSACTIONS,
        ]);
    }
}
