<?php

/*
 * AkahuNoteBuilder.php
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
