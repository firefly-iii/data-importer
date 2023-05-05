<?php
/*
 * camt.php
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

// all roles available for all CAMT data fields.
$availableRoles = [
    '_ignore'               => [
        'mappable'        => false,
        'pre-process-map' => false,
        'field'           => 'ignored',
        'converter'       => 'Ignore',
        'mapper'          => null,
        'append_value'    => false,
    ],
    'note'                  => [
        'mappable'        => false,
        'pre-process-map' => false,
        'field'           => 'note',
        'converter'       => 'CleanNlString',
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
    'account-iban'          => [
        'mappable'        => true,
        'pre-process-map' => false,
        'field'           => 'asset-account-iban',
        'converter'       => 'Iban',
        'mapper'          => 'AssetAccounts',
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
    'account-number'        => [
        'mappable'        => true,
        'pre-process-map' => false,
        'field'           => 'asset-account-number',
        'converter'       => 'CleanString',
        'mapper'          => 'AssetAccounts',
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
    'internal_reference'    => [
        'mappable'        => false,
        'pre-process-map' => false,
        'converter'       => 'Description',
        'field'           => 'internal_reference',
        'append_value'    => true,
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
    'currency-symbol'       => [
        'mappable'        => true,
        'pre-process-map' => false,
        'converter'       => 'CleanString',
        'field'           => 'currency',
        'mapper'          => 'TransactionCurrencies',
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
    'opposing-name'         => [
        'mappable'        => true,
        'pre-process-map' => false,
        'field'           => 'opposing-account-name',
        'converter'       => 'CleanString',
        'mapper'          => 'OpposingAccounts',
        'append_value'    => false,
    ],
    'description'           => [
        'mappable'        => false,
        'pre-process-map' => false,
        'converter'       => 'CleanString',
        'field'           => 'description',
        'append_value'    => true,
    ],
];


return [
    /*
     * Roles are divided into a number of groups,
     * i.e. the "level_a" group and the "dates" group etc.
     *
     * For each field in the CAMT file, it has been specified which of these groups apply.
     * This particular config can be found further ahead.
     *
     * Extra groups of roles can be created here, or existing groups extended.
     * For example, if you wish extract a field called "due date" from the CAMT file,
     * you could use existing group "dates" but you are free to make a new group called "due_date" with only one option.
     *
     * Make sure all groups also have the _ignore role as first option.
     */
    'roles'                 => [
        'level_a'        => [
            '_ignore' => $availableRoles['_ignore'],
            'note'    => $availableRoles['note'],
        ],
        // roles for any date field
        'dates'          => [
            '_ignore'          => $availableRoles['_ignore'],
            'date_transaction' => $availableRoles['date_transaction'],
            'date_interest'    => $availableRoles['date_interest'],
            'date_book'        => $availableRoles['date_book'],
            'date_process'     => $availableRoles['date_process'],
            'date_due'         => $availableRoles['date_due'],
            'date_payment'     => $availableRoles['date_payment'],
            'date_invoice'     => $availableRoles['date_invoice'],
        ],
        // roles for IBAN fields
        'iban'           => [
            '_ignore'       => $availableRoles['_ignore'],
            'account-iban'  => $availableRoles['account-iban'],
            'opposing-iban' => $availableRoles['opposing-iban'],
        ],
        // account number field roles
        'account_number' => [
            '_ignore'         => $availableRoles['_ignore'],
            'account-number'  => $availableRoles['account-number'],
            'opposing-number' => $availableRoles['opposing-number'],
        ],
        'account_name'   => [
            '_ignore'       => $availableRoles['_ignore'],
            'account-name'  => $availableRoles['account-name'],
            'opposing-name' => $availableRoles['opposing-name'],
        ],
        // roles for meta data
        'meta'           => [
            '_ignore'            => $availableRoles['_ignore'],
            'description'        => $availableRoles['description'],
            'note'               => $availableRoles['note'],
            'external-id'        => $availableRoles['external-id'],
            'external-url'       => $availableRoles['external-url'],
            'internal_reference' => $availableRoles['internal_reference'],
        ],
        'amount'         => [
            '_ignore'        => $availableRoles['_ignore'],
            'amount'         => $availableRoles['amount'],
            'amount_debit'   => $availableRoles['amount_debit'],
            'amount_credit'  => $availableRoles['amount_credit'],
            'amount_negated' => $availableRoles['amount_negated'],
            'amount_foreign' => $availableRoles['amount_foreign'],
        ],
        'currency'       => [
            '_ignore'               => $availableRoles['_ignore'],
            'currency-id'           => $availableRoles['currency-id'],
            'currency-name'         => $availableRoles['currency-name'],
            'currency-symbol'       => $availableRoles['currency-symbol'],
            'currency-code'         => $availableRoles['currency-code'],
            'foreign-currency-code' => $availableRoles['foreign-currency-code'],
        ],
    ],
    /*
     * This particular config variable holds all possible roles.
     */
    'all_roles'             => $availableRoles,

    /*
     * This array denotes all fields that can be extracted from a CAMT file and the necessary
     * configuration:
     */
    'fields'                => [
        // level A
        'messageId'                                                                      => [
            'title'        => 'messageId',
            'roles'        => 'level_a', // this is a reference to the role groups above.
            'mappable'     => false,
            'default_role' => 'note',
            'level'        => 'A',
        ],
        // level B, Statement
        'statementId'                                                          => [
            'title'        => 'statementId',
            'roles'        => 'level_b',
            'mappable'     => false,
            'default_role' => 'note',
            'level'        => 'B',
        ],
        'statementCreationDate'                                                          => [
            'title'        => 'statementCreationDate',
            'roles'        => 'dates',
            'mappable'     => false,
            'default_role' => 'date_process',
            'level'        => 'B',
        ],
        'statementAccountIban'                                                           => [
            'title'        => 'statementAccountIban',
            'roles'        => 'iban',
            'mappable'     => true,
            'default_role' => 'account-iban',
            'level'        => 'B',
        ],
        'statementAccountNumber'                                                         => [
            'title'        => 'statementAccountNumber',
            'roles'        => 'account_number',
            'mappable'     => true,
            'default_role' => 'account-number',
            'level'        => 'B',
        ],
        'entryDate'                                                                      => [
            'section'      => false,
            'title'        => 'entryDate',
            'default_role' => 'date_transaction',
            'roles'        => 'dates',
            'mappable'     => false,
            'level'        => 'C',
        ],
        // level C, Entry
        'entryAccountServicerReference'                                                  => [
            'section'      => false,
            'title'        => 'entryAccountServicerReference',
            'default_role' => 'external-id',
            'roles'        => 'meta',
            'mappable'     => false,
            'level'        => 'C',
        ],
        'entryReference'                                                                 => [
            'section'      => false,
            'title'        => 'entryReference',
            'default_role' => 'note',
            'roles'        => 'meta',
            'mappable'     => false,
            'level'        => 'C',
        ],
        'entryAdditionalInfo'                                                            => [
            'section'      => false,
            'title'        => 'entryAdditionalInfo',
            'default_role' => 'description',
            'roles'        => 'meta',
            'mappable'     => false,
            'level'        => 'C',
        ],
        'entryAmount'                                                                    => [
            'section'      => false,
            'title'        => 'entryAmount',
            'default_role' => 'amount',
            'roles'        => 'amount',
            'mappable'     => false,
            'level'        => 'C',
        ],
        'entryAmountCurrency'                                                            =>
            [
                'title'        => 'entryAmountCurrency',
                'default_role' => 'currency-code',
                'roles'        => 'currency',
                'mappable'     => true,
                'level'        => 'C',
            ],
        'entryValueDate'                                                                 =>
            [
                'title'        => 'entryValueDate',
                'default_role' => 'date_payment',
                'roles'        => 'dates',
                'mappable'     => false,
                'level'        => 'C',
            ],
        'entryBookingDate'                                                               =>
            [
                'title'        => 'entryBookingDate',
                'default_role' => 'date_book',
                'roles'        => 'dates',
                'mappable'     => false,
                'level'        => 'C',
            ],
        'entryBtcDomainCode'                                                             =>
            [
                'title'        => 'entryBtcDomainCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'C',
            ],
        'entryBtcFamilyCode'                                                             =>
            [
                'title'        => 'entryBtcFamilyCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'C',
            ],
        'entryBtcSubFamilyCode'                                                          =>
            [
                'title'        => 'entryBtcSubFamilyCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'C',
            ],
        'entryOpposingAccountIban'                                                       =>
            [
                'title'        => 'entryDetailOpposingAccountIban',
                'default_role' => 'opposing-iban',
                'roles'        => 'iban',
                'mappable'     => true,
                'level'        => 'C',
            ],
        'entryOpposingAccountNumber'                                                     =>
            [
                'title'        => 'entryDetailOpposingAccountNumber',
                'default_role' => 'opposing-number',
                'roles'        => 'account_number',
                'mappable'     => true,
                'level'        => 'C',
            ],
        'entryOpposingName'                                                              =>
            [
                'title'        => 'entryDetailOpposingName',
                'default_role' => 'opposing-name',
                'roles'        => 'account_name',
                'mappable'     => true,
                'level'        => 'C',
            ],

        // level D, entry detail
        'entryDetailAccountServicerReference'                                            =>
            [
                'title'        => 'entryDetailAccountServicerReference',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailRemittanceInformationUnstructuredBlockMessage'                       =>
            [
                'title'        => 'entryDetailRemittanceInformationUnstructuredBlockMessage',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation' =>
            [
                'title'        => 'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation',
                'default_role' => 'description',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],

        'entryDetailAmount'         =>
            [
                'title'        => 'entryDetailAmount',
                'default_role' => 'amount',
                'roles'        => 'amount',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailAmountCurrency' =>
            [
                'title'        => 'entryDetailAmountCurrency',
                'default_role' => 'currency-code',
                'roles'        => 'currency',
                'mappable'     => true,
                'level'        => 'D',
            ],

        'entryDetailBtcDomainCode'    =>
            [
                'title'        => 'entryBtcDomainCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailBtcFamilyCode'    =>
            [
                'title'        => 'entryDetailBtcFamilyCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailBtcSubFamilyCode' =>
            [
                'title'        => 'entryDetailBtcSubFamilyCode',
                'default_role' => 'note',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],

        'entryDetailOpposingAccountIban'   =>
            [
                'title'        => 'entryDetailOpposingAccountIban',
                'default_role' => 'opposing-iban',
                'roles'        => 'iban',
                'mappable'     => true,
                'level'        => 'D',
            ],
        'entryDetailOpposingAccountNumber' =>
            [
                'title'        => 'entryDetailOpposingAccountNumber',
                'default_role' => 'opposing-number',
                'roles'        => 'account_number',
                'mappable'     => true,
                'level'        => 'D',
            ],
        'entryDetailOpposingName'          =>
            [
                'title'        => 'entryDetailOpposingName',
                'default_role' => 'opposing-name',
                'roles'        => 'account_name',
                'mappable'     => true,
                'level'        => 'D',
            ],


    ],


    // TODO remove me
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
        'external-id'        => 'external_id',
        'external_id'        => 'external_id',
        'description'        => 'description_id',
        'internal_reference' => 'internal_reference',
        'internal-reference' => 'internal_reference',
    ],
];
