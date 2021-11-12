<?php
/**
 * transaction_types.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

use GrumpyDictator\FFIIIApiSupport\Model\AccountType;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionType;

return [
    // having the source + dest will tell you the transaction type.
    'account_to_transaction'    => [

        AccountType::ASSET           => [
            AccountType::ASSET           => TransactionType::TRANSFER,
            AccountType::CASH            => TransactionType::WITHDRAWAL,
            AccountType::DEBT            => TransactionType::WITHDRAWAL,
            AccountType::EXPENSE         => TransactionType::WITHDRAWAL,
            AccountType::INITIAL_BALANCE => TransactionType::OPENING_BALANCE,
            AccountType::LOAN            => TransactionType::WITHDRAWAL,
            AccountType::MORTGAGE        => TransactionType::WITHDRAWAL,
            AccountType::LIABILITIES     => TransactionType::WITHDRAWAL,
            AccountType::RECONCILIATION  => TransactionType::RECONCILIATION,
        ],
        AccountType::CASH            => [
            AccountType::ASSET => TransactionType::DEPOSIT,
        ],
        AccountType::DEBT            => [
            AccountType::ASSET           => TransactionType::DEPOSIT,
            AccountType::EXPENSE         => TransactionType::WITHDRAWAL,
            AccountType::INITIAL_BALANCE => TransactionType::OPENING_BALANCE,
            AccountType::DEBT            => TransactionType::TRANSFER,
            AccountType::LOAN            => TransactionType::TRANSFER,
            AccountType::MORTGAGE        => TransactionType::TRANSFER,
            AccountType::LIABILITIES     => TransactionType::TRANSFER,
        ],
        AccountType::LIABILITIES     => [
            AccountType::ASSET           => TransactionType::DEPOSIT,
            AccountType::EXPENSE         => TransactionType::WITHDRAWAL,
            AccountType::INITIAL_BALANCE => TransactionType::OPENING_BALANCE,
            AccountType::DEBT            => TransactionType::TRANSFER,
            AccountType::LOAN            => TransactionType::TRANSFER,
            AccountType::MORTGAGE        => TransactionType::TRANSFER,
            AccountType::LIABILITIES     => TransactionType::TRANSFER,
        ],
        AccountType::INITIAL_BALANCE => [
            AccountType::ASSET       => TransactionType::OPENING_BALANCE,
            AccountType::DEBT        => TransactionType::OPENING_BALANCE,
            AccountType::LOAN        => TransactionType::OPENING_BALANCE,
            AccountType::MORTGAGE    => TransactionType::OPENING_BALANCE,
            AccountType::LIABILITIES => TransactionType::OPENING_BALANCE,
        ],
        AccountType::LOAN            => [
            AccountType::ASSET           => TransactionType::DEPOSIT,
            AccountType::EXPENSE         => TransactionType::WITHDRAWAL,
            AccountType::INITIAL_BALANCE => TransactionType::OPENING_BALANCE,
            AccountType::DEBT            => TransactionType::TRANSFER,
            AccountType::LOAN            => TransactionType::TRANSFER,
            AccountType::MORTGAGE        => TransactionType::TRANSFER,
            AccountType::LIABILITIES     => TransactionType::TRANSFER,
        ],
        AccountType::MORTGAGE        => [
            AccountType::ASSET           => TransactionType::DEPOSIT,
            AccountType::EXPENSE         => TransactionType::WITHDRAWAL,
            AccountType::INITIAL_BALANCE => TransactionType::OPENING_BALANCE,
            AccountType::DEBT            => TransactionType::TRANSFER,
            AccountType::LOAN            => TransactionType::TRANSFER,
            AccountType::MORTGAGE        => TransactionType::TRANSFER,
            AccountType::LIABILITIES     => TransactionType::TRANSFER,
        ],
        AccountType::RECONCILIATION  => [
            AccountType::ASSET => TransactionType::RECONCILIATION,
        ],
        AccountType::REVENUE         => [
            AccountType::ASSET       => TransactionType::DEPOSIT,
            AccountType::DEBT        => TransactionType::DEPOSIT,
            AccountType::LOAN        => TransactionType::DEPOSIT,
            AccountType::MORTGAGE    => TransactionType::DEPOSIT,
            AccountType::LIABILITIES => TransactionType::DEPOSIT,
        ],
    ],
];
