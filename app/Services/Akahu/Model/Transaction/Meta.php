<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;



// https://developers.akahu.nz/docs/the-transaction-model#meta
// Additional metadata we manage to retrieve for the transaction. Only present
// when you have permission to view enriched transactions. All fields are optional
// and provided on a "best-effort" basis.
final class Meta
{
    // The particulars field set on this transaction.
    private ?string $particulars = null;

    // The code field set on this transaction.
    private ?string $code = null;

    // The reference field set on this transaction.
    private ?string $reference = null;

    // The formatted NZ bank account number of the other party to this transaction
    private ?string $otherAccount = null;

    // If this transaction was made in another currency, details about the currency conversion.
    private ?Conversion $conversion = null;

    // If this transaction was made with a credit or debit card, the last four digits of the
    // card number.
    private ?string $cardSuffix = null;

    // URL of a .png image for this transaction. This is typically the logo of the transaction
    // merchant. If no logo is available, a placeholder image is provided.
    private ?string $logo = null;

    /**
     * Parse a meta structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $meta = new self();

        $meta->particulars = $json['particulars'] ?? null;
        $meta->code = $json['code'] ?? null;
        $meta->reference = $json['reference'] ?? null;

        $meta->otherAccount = $json['other_account'] ?? null;

        $meta->conversion = array_key_exists('conversion', $json) ? Conversion::fromJson($json['conversion']) : null;

        $meta->cardSuffix = $json['card_suffix'] ?? null;

        $meta->logo = $json['logo'] ?? null;

        return $meta;
    }

    public function getParticulars(): ?string
    {
        return $this->particulars;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getOtherAccount(): ?string
    {
        return $this->otherAccount;
    }

    public function getConversion(): ?Conversion
    {
        return $this->conversion;
    }

    public function getCardSuffix(): ?string
    {
        return $this->cardSuffix;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }
}
