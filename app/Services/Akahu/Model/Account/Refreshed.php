<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Account;

use Carbon\Carbon;

final class Refreshed
{
    // When the balance was last updated.
    private ?Carbon $balance = null;

    // When other account metadata was last updated (any account property
    // apart from balance).
    private ?Carbon $meta = null;

    // When we last checked for and processed any new transactions. This
    // flag may be missing when an account has first connected, as it takes
    // a few seconds for new transactions to be processed.
    private ?Carbon $transactions = null;

    // When we last fetched identity data about the party who has
    // authenticated with the financial institution when connecting this
    // account. This data is updated by Akahu on a fixed 30 day interval,
    // regardless of your app's data refresh configuration.
    private ?Carbon $party = null;

    /**
     * Parse a refreshed structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $refreshed = new self();

        $refreshed->balance = isset($json['balance'])
            ? Carbon::parse($json['balance']) : null;
        $refreshed->meta = isset($json['meta'])
            ? Carbon::parse($json['meta']) : null;
        $refreshed->transactions = isset($json['transactions'])
            ? Carbon::parse($json['transactions']) : null;
        $refreshed->party = isset($json['party'])
            ? Carbon::parse($json['party']) : null;

        return $refreshed;
    }

    /**
     * Serialize a refreshed structure to store on disk
     */
    public function toArray(): array
    {
        return array(
            'balance' => $this->balance?->toISOString(),
            'meta' => $this->meta?->toISOString(),
            'transactions' => $this->transactions?->toISOString(),
            'party' => $this->party?->toISOString(),
        );
    }

    /**
     * Deserialize a refreshed structure from disk
     */
    public static function fromArray(array $data): self
    {
        $refreshed = new self();

        $refreshed->balance = isset($data['balance'])
            ? Carbon::parse($data['balance']) : null;
        $refreshed->meta = isset($data['meta'])
            ? Carbon::parse($data['meta']) : null;
        $refreshed->transactions = isset($data['transactions'])
            ? Carbon::parse($data['transactions']) : null;
        $refreshed->party = isset($data['party'])
            ? Carbon::parse($data['party']) : null;

        return $refreshed;
    }

    public function getBalance(): ?Carbon
    {
        return $this->balance;
    }

    public function getMeta(): ?Carbon
    {
        return $this->meta;
    }

    public function getTransactions(): ?Carbon
    {
        return $this->transactions;
    }

    public function getParty(): ?Carbon
    {
        return $this->party;
    }
}
