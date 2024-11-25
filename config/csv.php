<?php

/*
 * csv.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

use App\Services\CSV\Conversion\Task\Accounts;
use App\Services\CSV\Conversion\Task\Amount;
use App\Services\CSV\Conversion\Task\Currency;
use App\Services\CSV\Conversion\Task\EmptyAccounts;
use App\Services\CSV\Conversion\Task\EmptyDescription;
use App\Services\CSV\Conversion\Task\PositiveAmount;
use App\Services\CSV\Conversion\Task\Tags;

return [
    'delimiters'            => [
        'comma'     => ',',
        'semicolon' => ';',
        'tab'       => "\t",
        ','         => ',',
        ';'         => ';',
        "\t"        => "\t",
    ],
    'delimiters_reversed'   => [
        'comma'     => 'comma',
        'semicolon' => 'semicolon',
        'tab'       => 'tab',
        ','         => 'comma',
        ';'         => 'semicolon',
        "\t"        => 'tab',
    ],
    // csv config
    'classic_roles'         => [
        'original-source'    => 'original_source',
        'sepa-cc'            => 'sepa_cc',
        'sepa-ct-op'         => 'sepa_ct_op',
        'sepa-ct-id'         => 'sepa_ct_id',
        'sepa-db'            => 'sepa_db',
        'sepa-country'       => 'sepa_country',
        'sepa-ep'            => 'sepa_ep',
        'sepa-ci'            => 'sepa_ci',
        'sepa-batch-id'      => 'sepa_batch_id',
        'internal-reference' => 'internal_reference',
        'date-interest'      => 'date_interest',
        'date-invoice'       => 'date_invoice',
        'date-book'          => 'date_book',
        'date-payment'       => 'date_payment',
        'date-process'       => 'date_process',
        'date-due'           => 'date_due',
        'date-transaction'   => 'date_transaction',
    ],
    'transaction_tasks'     => [
        Amount::class,
        Tags::class,
        Currency::class,
        Accounts::class,
        PositiveAmount::class,
        EmptyDescription::class,
        EmptyAccounts::class,
    ],

    /*
     * Configuration for possible column roles.
     *
     * The key is the short name for the column role. There are five values, which mean this:
     *
     * 'mappable'
     * Whether or not the value in the CSV column can be linked to an existing value in your
     * Firefly database. For example: account names can be linked to existing account names you have already
     * so double entries cannot occur. This process is called "mapping". You have to make each unique value in your
     * CSV file to an existing entry in your database. For example, map all account names in your CSV file to existing
     * accounts. If you have an entry that does not exist in your database, you can set Firefly to ignore it, and it will
     * create it.
     *
     * 'pre-process-map'
     * In the case of tags, there are multiple values in one csv column (for example: "expense groceries snack" in one column).
     * This means the content of the column must be "pre processed" aka split in parts so the importer can work with the data.
     *
     * 'pre-process-mapper'
     * This is the class that will actually do the pre-processing.
     *
     * 'field'
     * I don't believe this value is used any more, but I am not sure.
     *
     * 'converter'
     * The converter is a class in app/Service/CSV/Converter that converts the given value into an object Firefly understands.
     * The CategoryName converter can convert a category name into an actual category. This converter will take a mapping
     * into account: if you mapped "Groceries" to category "Groceries" the converter will simply return "Groceries" instead of
     * trying to make a new category also named Groceries.
     *
     * 'mapper'
     * When you map data (see "mappable") you need a list of stuff you can map to. If you say a certain column is mappable
     * and the column contains "category names", the mapper will be "Category" and it will give you a list of possible categories.
     * This way the importer always presents you with a valid list of things to map to.
     *
     *
     *
     */
    'import_roles'          => [
        '_ignore'               => [
            'mappable'        => false,
            'pre-process-map' => false,
            'field'           => 'ignored',
            'converter'       => 'Ignore',
            'mapper'          => null,
            'append_value'    => false,
        ],
        'bill-id'               => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'bill',
            'converter'       => 'CleanId',
            'mapper'          => 'Bills',
            'append_value'    => false,
        ],
        'note'                  => [
            'mappable'        => false,
            'pre-process-map' => false,
            'field'           => 'note',
            'converter'       => 'CleanNlString',
            'append_value'    => true,
        ],
        'bill-name'             => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'bill',
            'converter'       => 'CleanString',
            'mapper'          => 'Bills',
            'append_value'    => false,
        ],
        'currency-id'           => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'currency',
            'converter'       => 'CleanId',
            'mapper'          => 'TransactionCurrencies',
            'append_value'    => false,
        ],
        'currency-name'         => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'currency',
            'mapper'          => 'TransactionCurrencies',
            'append_value'    => false,
        ],
        'currency-code'         => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'currency',
            'mapper'          => 'TransactionCurrencies',
            'append_value'    => false,
        ],
        'foreign-currency-code' => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'foreign_currency',
            'mapper'          => 'TransactionCurrencies',
            'append_value'    => false,
        ],
        'external-id'           => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'external-id',
            'append_value'    => false,
        ],
        'external-url'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'CleanUrl',
            'field'           => 'external-url',
            'append_value'    => false,
        ],
        'currency-symbol'       => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'currency',
            'mapper'          => 'TransactionCurrencies',
            'append_value'    => false,
        ],
        'description'           => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'description',
            'append_value'    => true,
        ],
        'date_transaction'      => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date',
            'append_value'    => false,
        ],
        'date_interest'         => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-interest',
            'append_value'    => false,
        ],
        'date_book'             => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-book',
            'append_value'    => false,
        ],
        'date_process'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-process',
            'append_value'    => false,
        ],
        'date_due'              => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-due',
            'append_value'    => false,
        ],
        'date_payment'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-payment',
            'append_value'    => false,
        ],
        'date_invoice'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Date',
            'field'           => 'date-invoice',
            'append_value'    => false,
        ],
        'budget-id'             => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanId',
            'field'           => 'budget',
            'mapper'          => 'Budgets',
            'append_value'    => false,
        ],
        'budget-name'           => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'budget',
            'mapper'          => 'Budgets',
            'append_value'    => false,
        ],
        'rabo-debit-credit'     => [
            'mappable   '     => false,
            'pre-process-map' => false,
            'converter'       => 'BankDebitCredit',
            'field'           => 'amount-modifier',
            'append_value'    => false,
        ],
        'ing-debit-credit'      => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'BankDebitCredit',
            'field'           => 'amount-modifier',
            'append_value'    => false,
        ],
        'generic-debit-credit'  => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'BankDebitCredit',
            'field'           => 'amount-modifier',
            'append_value'    => false,
        ],
        'category-id'           => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanId',
            'field'           => 'category',
            'mapper'          => 'Categories',
            'append_value'    => false,
        ],
        'category-name'         => [
            'mappable'        => true,
            'pre-process-map' => false,
            'converter'       => 'CleanString',
            'field'           => 'category',
            'mapper'          => 'Categories',
            'append_value'    => false,
        ],
        'tags-comma'            => [
            'mappable'           => false,
            'pre-process-map'    => true,
            'pre-process-mapper' => 'TagsComma',
            'field'              => 'tags',
            'converter'          => 'TagsComma',
            'mapper'             => 'Tags',
            'append_value'       => true,
        ],
        'tags-space'            => [
            'mappable'           => false,
            'pre-process-map'    => true,
            'pre-process-mapper' => 'TagsSpace',
            'field'              => 'tags',
            'converter'          => 'TagsSpace',
            'mapper'             => 'Tags',
            'append_value'       => true,
        ],
        'account-id'            => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'asset-account-id',
            'converter'       => 'CleanId',
            'mapper'          => 'AssetAccounts',
            'append_value'    => false,
        ],
        'account-name'          => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'asset-account-name',
            'converter'       => 'CleanString',
            'mapper'          => 'AssetAccounts',
            'append_value'    => false,
        ],
        'account-iban'          => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'asset-account-iban',
            'converter'       => 'Iban',
            'mapper'          => 'AssetAccounts',
            'append_value'    => false,
        ],
        'account-number'        => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'asset-account-number',
            'converter'       => 'CleanString',
            'mapper'          => 'AssetAccounts',
            'append_value'    => false,
        ],
        'account-bic'           => [
            'mappable'        => false,
            'pre-process-map' => false,
            'field'           => 'asset-account-bic',
            'converter'       => 'CleanString',
            'append_value'    => false,
        ],
        'opposing-id'           => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'opposing-account-id',
            'converter'       => 'CleanId',
            'mapper'          => 'OpposingAccounts',
            'append_value'    => false,
        ],
        'opposing-bic'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'field'           => 'opposing-account-bic',
            'converter'       => 'CleanString',
            'append_value'    => false,
        ],
        'opposing-name'         => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'opposing-account-name',
            'converter'       => 'CleanString',
            'mapper'          => 'OpposingAccounts',
            'append_value'    => false,
        ],
        'opposing-iban'         => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'opposing-account-iban',
            'converter'       => 'Iban',
            'mapper'          => 'OpposingAccounts',
            'append_value'    => false,
        ],
        'opposing-number'       => [
            'mappable'        => true,
            'pre-process-map' => false,
            'field'           => 'opposing-account-number',
            'converter'       => 'CleanString',
            'mapper'          => 'OpposingAccounts',
            'append_value'    => false,
        ],
        'amount'                => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Amount',
            'field'           => 'amount',
            'append_value'    => false,
        ],
        'amount_debit'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'AmountDebit',
            'field'           => 'amount_debit',
            'append_value'    => false,
        ],
        'amount_credit'         => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'AmountCredit',
            'field'           => 'amount_credit',
            'append_value'    => false,
        ],
        'amount_negated'        => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'AmountNegated',
            'field'           => 'amount_negated',
            'append_value'    => false,
        ],
        'amount_foreign'        => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Amount',
            'field'           => 'amount_foreign',
            'append_value'    => false,
        ],
        'sepa_ct_id'            => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_ct_id',
            'append_value'    => false,
        ],
        'sepa_ct_op'            => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_ct_op',
            'append_value'    => false,
        ],
        'sepa_db'               => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_db',
            'append_value'    => false,
        ],
        'sepa_cc'               => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_cc',
            'append_value'    => false,
        ],
        'sepa_country'          => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_country',
            'append_value'    => false,
        ],
        'sepa_ep'               => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_ep',
            'append_value'    => false,
        ],
        'sepa_ci'               => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_ci',
            'append_value'    => false,
        ],
        'sepa_batch_id'         => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'sepa_batch',
            'append_value'    => false,
        ],
        'internal_reference'    => [
            'mappable'        => false,
            'pre-process-map' => false,
            'converter'       => 'Description',
            'field'           => 'internal_reference',
            'append_value'    => true,
        ],
    ],
    'role_to_transaction'   => [
        'account-id'            => 'source_id',
        'account-iban'          => 'source_iban',
        'account-name'          => 'source_name',
        'account-number'        => 'source_number',
        'account-bic'           => 'source_bic',
        'opposing-id'           => 'destination_id',
        'opposing-iban'         => 'destination_iban',
        'opposing-name'         => 'destination_name',
        'opposing-number'       => 'destination_number',
        'opposing-bic'          => 'destination_bic',
        'sepa_cc'               => 'sepa_cc',
        'sepa_ct_op'            => 'sepa_ct_op',
        'sepa_ct_id'            => 'sepa_ct_id',
        'sepa_db'               => 'sepa_db',
        'sepa_country'          => 'sepa_country',
        'sepa_ep'               => 'sepa_ep',
        'sepa_ci'               => 'sepa_ci',
        'sepa_batch_id'         => 'sepa_batch_id',
        'amount'                => 'amount',
        'amount_debit'          => 'amount_debit',
        'amount_credit'         => 'amount_credit',
        'amount_negated'        => 'amount_negated',
        'amount_foreign'        => 'foreign_amount',
        'foreign-currency-id'   => 'foreign_currency_id',
        'foreign-currency-code' => 'foreign_currency_code',
        'bill-id'               => 'bill_id',
        'bill-name'             => 'bill_name',
        'budget-id'             => 'budget_id',
        'budget-name'           => 'budget_name',
        'category-id'           => 'category_id',
        'category-name'         => 'category_name',
        'currency-id'           => 'currency_id',
        'currency-name'         => 'currency_name',
        'currency-symbol'       => 'currency_symbol',
        'description'           => 'description',
        'note'                  => 'notes',
        'ing-debit-credit'      => 'amount_modifier',
        'rabo-debit-credit'     => 'amount_modifier',
        'generic-debit-credit'  => 'amount_modifier',
        'external-id'           => 'external_id',
        'external-url'          => 'external_url',
        'internal_reference'    => 'internal_reference',
        'original-source'       => 'original_source',
        'tags-comma'            => 'tags_comma',
        'tags-space'            => 'tags_space',
        'date_transaction'      => 'date',
        'date_interest'         => 'interest_date',
        'date_book'             => 'book_date',
        'date_process'          => 'process_date',
        'date_due'              => 'due_date',
        'date_payment'          => 'payment_date',
        'date_invoice'          => 'invoice_date',
        'currency-code'         => 'currency_code',
    ],
    'unique_column_options' => [
        'note'               => 'The notes',
        'external-id'        => 'External identifier',
        'description'        => 'Transaction description',
        'internal_reference' => 'Internal reference',
    ],
    'search_modifier'       => [
        'note'               => 'notes_are',
        'external-id'        => 'external_id_is',
        'external_id'        => 'external_id_is',
        'description'        => 'description_is',
        'internal_reference' => 'internal_reference_is',
        'internal-reference' => 'internal_reference_is',
    ],
];
