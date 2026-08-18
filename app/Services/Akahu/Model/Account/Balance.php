<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Account;


use BcMath\Number;
use App\Support\Facades\Steam;


final class Balance
{
    // The current account balance. A negative balance indicates the amount owed
    // to the account issuer. For example a checking account in overdraft will have
    // a negative balance, same as the amount owed on a credit card or the
    // principal remaining on a loan.
    private ?Number $current = null;

    // The balance that is currently available to the account holder.
    private ?Number $available = null;

    // The credit limit for this account. For example a credit card limit or an
    // overdraft limit. This value is only present when provided directly by the
    // connected financial institution.
    private ?Number $limit = null;

    // A boolean indicating whether this account is in overdraft.
    private ?bool $overdrawn = null;

    // The 3 letter ISO 4217 currency code that this balance is in (e.g. NZD).
    private ?string $currency = null;

    /**
     * Parse a balance structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $balance = new self();

        $balance->current = array_key_exists('current', $json)
            ? Steam::bcnumber($json['current']) : null;
        $balance->available = array_key_exists('available', $json)
            ? Steam::bcnumber($json['available']) : null;
        $balance->limit = array_key_exists('limit', $json)
            ? Steam::bcnumber($json['limit']) : null;

        $balance->overdrawn = $json['overdrawn'] ?? null;
        $balance->currency = $json['currency'] ?? null;

        return $balance;
    }

    /**
     * Serialize a balance structure to store on disk
     */
    public function toArray(): array
    {
        return [
            'current' => serialize($this->current),
            'available' => serialize($this->available),
            'limit' => serialize($this->limit),
            'overdrawn' => $this->overdrawn,
            'currency' => $this->currency,
        ];
    }

    /**
     * Deserialize a balance structure from disk
     */
    public static function fromArray(array $data): self
    {
        $balance = new self();

        $balance->current = array_key_exists('current', $data) ? unserialize($data['current']) : null;
        $balance->available = array_key_exists('available', $data) ? unserialize($data['available']) : null;
        $balance->limit = array_key_exists('limit', $data)? unserialize($data['limit']) : null;

        $balance->overdrawn = $data['overdrawn'] ?? null;
        $balance->currency = $data['currency'] ?? null;

        return $balance;
    }

    public function getCurrent(): ?Number
    {
        return $this->current;
    }

    public function getAvailable(): ?Number
    {
        return $this->available;
    }

    public function getLimit(): ?Number
    {
        return $this->limit;
    }

    public function getOverdrawn(): ?bool
    {
        return $this->overdrawn;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
