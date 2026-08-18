<?php

declare(strict_types=1);

namespace App\Services\Akahu\Conversion;

use App\Services\Akahu\Model\Transaction\Transaction;
use App\Services\Shared\Conversion\Field;
use App\Services\Shared\Conversion\NoteBuilder;

final class AkahuNoteBuilder extends NoteBuilder
{
    private ?Transaction $transaction = null;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Builds notes on this transaction to give to Firefly III
     */
    public function build(): string
    {
        $meta     = $this->transaction->getMeta();

        $this->renderSection('Transaction Metadata', [
            new Field('Particulars', $meta?->getParticulars()),
            new Field('Code', $meta?->getCode()),
            new Field('Reference', $meta?->getReference()),
            new Field('Other account', $meta?->getOtherAccount()),
        ]);

        $this->renderSection('Eftpos Infomation', [new Field('Card Suffix', $meta?->getCardSuffix()), new Field('Logo', $meta?->getLogo())]);

        $merchant = $this->transaction->getMerchant();

        $this->renderSection('Merchant Infomation', [
            new Field('Name', $merchant?->getName()),
            new Field('Website', $merchant?->getWebsite()),
            new Field('NZBN (https://www.nzbn.govt.nz/)', $merchant?->getNZBN()),
        ]);

        $category = $this->transaction->getCategory();

        $this->renderSection('Akahu NZFCC classification (https://nzfcc.org/)', [
            new Field('Name', $category?->getName()),
            new Field('Personal finance category', $category?->getPersonalFinanceGroup()?->getName()),
        ]);

        return $this->getNotes();
    }
}
