<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Account;



final class Account
{
    public const string ATTRIBUTE_TRANSACTIONS = 'TRANSACTIONS';
    public const string ATTRIBUTE_PAYMENT_TO = 'PAYMENT_TO';
    public const string ATTRIBUTE_PAYMENT_FROM = 'PAYMENT_FROM';

    // The _id key is a unique identifier for the account in the Akahu system. It
    // is always be prefixed by acc_ so that you can tell that it belongs to an account.
    private ?string $akahuId = null;

    // This tells you who the original account provider is (e.g. Demo Bank).
    private ?Connection $connection = null;

    // This is the name of the account.
    private ?string $name = null;

    // This attribute indicates the status of Akahu's connection to this account.
    // It is possible for Akahu to lose the ability to authenticate with a financial
    // institution if the user revokes Akahu's access directly via their institution, or
    // changes their login credentials, which in some cases can cause our long-lived
    // access to be revoked.
    private ?string $akahuStatus = null;

    // If the account has a well defined account number (eg. a bank account number, or
    // credit card number) this will be defined here with a standard format across
    // connections. This field will be the value `undefined` for accounts with KiwiSaver
    // providers and investment platform accounts.
    //
    // For NZ banks, we use the common format `00-0000-0000000-00`.
    // For credit cards, we return a redacted card number `1234-****-****-1234`
    // or `****-****-****-1234`
    private ?string $formattedAccount = null;

    // This is a less defined part of our API that lets us expose data that may be
    // specific to certain account types or financial institutions. An investment
    // provider, for example, may expose a breakdown of investment results.
    private ?array $meta = null;

    // https://developers.akahu.nz/docs/the-account-model#payment_consents
    // `payment_consents` is omitted as Firefly III makes no payments on the users behalf

    // Akahu can refresh different parts of an account's data at different rates. The
    // timestamps in the refreshed object tell you when that account data was last
    // updated. When looking at a timestamp in here, you can think "Akahu's view of the
    // account (balance/metadata/transactions) is up to date as of $TIME".
    private ?Refreshed $refreshed = null;

    // The account balance.
    private ?Balance $balance = null;

    // What sort of account this is. Akahu provides specific bank account types, and falls
    // back to more general types for other types of connection.
    private ?string $type = null;

    // The list of attributes indicates what abilities an account has.
    private ?array $attributes = null;

    // https://developers.akahu.nz/docs/the-account-model#status
    private const array AKAHU_STATUS_MAP = [
        // Akahu can authenticate with the institution to retrieve data and/or
        // initiate payments for this account.
        'ACTIVE' => 'enabled',

        // Akahu no longer has access to this account. Your application will
        // still be able to access Akahu's cached copy of data for this account,
        // but this will no longer be updated by refreshes. Write actions such
        // as payments will no longer be available. Once an account is assigned
        // the INACTIVE status, it will stay this way until the user re-establishes
        // the connection. When your application observes an account with a
        // status of INACTIVE, the user should be directed back to the Akahu
        // OAuth flow or to https://my.akahu.nz/connections where they will be
        // prompted to re-establish the connection.
        'INACTIVE' => 'disabled',
    ];

    /**
     * Parse an account structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $account = new self();

        $account->akahuId = $json['_id'] ?? null;

        $account->connection = array_key_exists('connection', $json)
            ? Connection::fromJson($json['connection']) : null;

        $account->name = $json['name'] ?? null;
        $account->akahuStatus = $json['status'] ?? null;
        $account->formattedAccount = $json['formatted_account'] ?? null;

        $account->meta = $json['meta'] ?? null;

        $account->refreshed = array_key_exists('refreshed', $json)
            ? Refreshed::fromJson($json['refreshed']) : null;

        $account->balance = array_key_exists('balance', $json)
            ? Balance::fromJson($json['balance']) : null;

        $account->type = $json['type'] ?? null;
        $account->attributes = $json['attributes'] ?? null;

        return $account;
    }

    /**
     * Serialize an account structure to store on disk
     */
    public function toArray(): array
    {
        return array(
            'class'            => self::class,
            'akahuId'          => $this->akahuId,
            'connection'       => $this->connection?->toArray(),
            'name'             => $this->name,
            'akahuStatus'      => $this->akahuStatus,
            'formattedAccount' => $this->formattedAccount,
            'meta'             => $this->meta,
            'refreshed'        => $this->refreshed?->toArray(),
            'balance'          => $this->balance?->toArray(),
            'type'             => $this->type,
            'attributes'       => $this->attributes,
        );
    }

    /**
     * Deserialize an account structure from disk
     */
    public static function fromArray(array $data): self
    {
        $account = new self();

        $account->akahuId = $data['akahuId'] ?? null;

        $account->connection = array_key_exists('connection', $data)
            ? Connection::fromArray($data['connection']) : null;

        $account->name = $data['name'] ?? null;
        $account->akahuStatus = $data['akahuStatus'] ?? null;
        $account->formattedAccount = $data['formattedAccount'] ?? null;
        $account->meta = $data['meta'] ?? null;

        $account->refreshed = array_key_exists('refreshed', $data)
            ? Refreshed::fromArray($data['refreshed']) : null;

        $account->balance = array_key_exists('balance', $data)
            ? Balance::fromArray($data['balance']) : null;

        $account->type = $data['type'] ?? null;

        $account->attributes = $data['attributes'] ?? null;

        return $account;
    }

    public function getAkahuId(): ?string
    {
        return $this->akahuId;
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAkahuStatus(): ?string
    {
        return $this->akahuStatus;
    }

    public function getFormattedAccount(): ?string
    {
        return $this->formattedAccount;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function getRefreshed(): ?Refreshed
    {
        return $this->refreshed;
    }

    public function getBalance(): ?Balance
    {
        return $this->balance;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    
    // Helpers
    

    public function getFireflyStatus(): string
    {
        return self::AKAHU_STATUS_MAP[$this->getAkahuStatus()];
    }

}
