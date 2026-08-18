<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;

// Akahu defines a merchant as the business who was party to this transaction.
// For example, "The Warehouse" is a merchant.
final class Merchant
{
    // The Akahu Merchant ID
    private ?string $akahuId = null;

    // The Akahu Merchant name
    private ?string $name    = null;

    // The Akahu Merchant website
    private ?string $website = null;

    // Undocumented
    // https://www.nzbn.govt.nz/
    private ?string $nzbn    = null;

    /**
     * Parse a merchant structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $merchant          = new self();

        $merchant->akahuId = $json['_id'] ?? null;
        $merchant->name    = $json['name'] ?? null;
        $merchant->website = $json['website'] ?? null;
        $merchant->nzbn    = $json['nzbn'] ?? null;

        return $merchant;
    }

    public function getAkahuId(): ?string
    {
        return $this->akahuId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getNZBN(): ?string
    {
        return $this->nzbn;
    }
}
