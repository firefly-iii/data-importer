<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;

use Carbon\Carbon;
use App\Services\Akahu\Model\Transaction\Meta;
use InvalidArgumentException;
use App\Support\Facades\Steam;
use BcMath\Number;

final class Transaction
{
    // he _id key is a unique identifier for the transaction in the Akahu system.
    // It is always be prefixed by trans_ so that you can tell that it refers to a transaction.
    private ?string $akahuId     = null;

    // The _account key indicates which account this transaction belongs to.
    private ?string $akahuAccountId = null;

    // The time that Akahu first saw this transaction (as an ISO 8601 timestamp). This
    // is unrelated to the transaction date (when the transaction occurred) because Akahu
    // may have retrieved an old transaction.
    private ?Carbon $createdAt     = null;

    // An ISO 8601 timestamp. In many cases this will only be accurate to the day, due
    // to the level of detail provided by the bank. Where available, the date is the
    // date that the transaction took place, but if that information is unavailable it
    // will be the date that the transaction was settled by the bank.
    private ?Carbon $date     = null;

    // The transacton description as provided by the bank. Some minor cleanup is done
    // by Akahu (such as whitespace normalisation), but this value is otherwise direct
    // from the bank.
    private ?string $description     = null;

    // The amount of money that was moved by this transaction.
    private ?Number $amount     = null;

    // If available, the account balance immediately after this transaction was made.
    // This value is direct from the bank and not modified by Akahu.
    private ?Number $balance     = null;

    // https://developers.akahu.nz/docs/the-transaction-model#type
    // What sort of transaction this is. Akahu tries to find a specific transaction
    // type, falling back to "CREDIT" or "DEBIT" if nothing else is available.
    private ?string $type     = null;

    //
    // Akahu "Enriched transaction data"
    //

    // The category object categorises the transaction using NZFCC codes
    // (New Zealand Financial Category Codes)
    private ?Category $category = null;

    // Akahu defines a merchant as the business who was party to this transaction. For
    // example, "The Warehouse" is a merchant.
    private ?Merchant $merchant = null;

    // https://developers.akahu.nz/docs/the-transaction-model#meta
    // This is other metadata that we extract from the transaction. All of the meta
    // fields are optional.
    private ?Meta $meta = null;

    /**
     * Parse a transaction structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $transaction = new self();

        $transaction->akahuId = $json['_id'] ?? null;
        $transaction->akahuAccountId = $json['_account']
            ?? throw new InvalidArgumentException('transaction contains no account id');

        $createdAt = $json['created_at'] ?? null;
        $transaction->createdAt = Carbon::parse($createdAt);

        $date = $json['date']
            ?? throw new InvalidArgumentException('transaction contains no date');
        $transaction->date = Carbon::parse($date);

        $transaction->description = $json['description']
            ?? throw new InvalidArgumentException('transaction contains no description');
        $transaction->amount = array_key_exists('amount', $json)
            ? Steam::bcnumber($json['amount'])
                : throw new InvalidArgumentException('transaction contains no amount');

        $transaction->balance = array_key_exists('balance', $json)
            ? Steam::bcnumber($json['balance']) : null;
        $transaction->type = $json['type']
            ?? throw new InvalidArgumentException('transaction contains no type');
        $transaction->category = array_key_exists('category', $json)
            ? Category::fromJson($json['category']) : null;
        $transaction->merchant = array_key_exists('merchant', $json)
            ? Merchant::fromJson($json['merchant']) : null;
        $transaction->meta = array_key_exists('meta', $json)
            ? Meta::fromJson($json['meta']) : null;

        return $transaction;
    }

    public function getAkahuId(): ?string
    {
        return $this->akahuId;
    }

    public function getAkahuAccountId(): string
    {
        return $this->akahuAccountId;
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->createdAt;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAmount(): ?Number
    {
        return $this->amount;
    }

    public function getBalance(): ?Number
    {
        return $this->balance;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function getMerchant(): ?Merchant
    {
        return $this->merchant;
    }

    public function getMeta(): ?Meta
    {
        return $this->meta;
    }
}
