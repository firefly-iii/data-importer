<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;

use App\Support\Facades\Steam;
use BcMath\Number;

// https://developers.akahu.nz/docs/the-transaction-model#meta
// If this transaction was made in another currency, details about the currency conversion.
final class Conversion
{
    // The amount transacted in the foreign currency
    private ?Number $amount   = null;

    // The (3 letter ISO 4217 currency code)[https://www.xe.com/iso4217.php]
    // that was used for this transaction.
    private ?string $currency = null;

    // The foreign currency conversion rate applied to this transaction.
    private ?Number $rate     = null;

    // Undocumented
    private ?Number $fee      = null;

    /**
     * Parse a conversion structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $conversion           = new self();

        $conversion->amount   = array_key_exists('amount', $json) ? Steam::bcnumber($json['amount']) : null;
        $conversion->currency = $json['currency'] ?? null;
        $conversion->rate     = array_key_exists('rate', $json) ? Steam::bcnumber($json['rate']) : null;
        $conversion->fee      = array_key_exists('fee', $json) ? Steam::bcnumber($json['fee']) : null;

        return $conversion;
    }

    public function getAmount(): ?Number
    {
        return $this->amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getRate(): ?Number
    {
        return $this->rate;
    }

    public function getFee(): ?Number
    {
        return $this->fee;
    }
}
