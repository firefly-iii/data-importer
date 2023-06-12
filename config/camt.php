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
    'generic-debit-credit'  => [
        'mappable'        => false,
        'pre-process-map' => false,
        'converter'       => 'BankDebitCredit',
        'field'           => 'amount-modifier',
        'append_value'    => false,
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
    'roles'     => [
        'level_a'        => [
            '_ignore' => $availableRoles['_ignore'],
            'note'    => $availableRoles['note'],
        ],
        'level_b'        => [
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
    'all_roles' => $availableRoles,

    /*
     * This array denotes all fields that can be extracted from a CAMT file and the necessary
     * configuration:
     */
    'fields'    => [
        // level A
        'messageId'                                                                      => [
            'title'        => 'messageId',
            'roles'        => 'level_a', // this is a reference to the role groups above.
            'mappable'     => false,
            'default_role' => 'note',
            'level'        => 'A',
        ],
        // level B, Statement
        'statementId'                                                                    => [
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

        // level D, entry detail
        'entryDetailAccountServicerReference'                                            =>
            [
                'title'        => 'entryDetailAccountServicerReference',
                'default_role' => 'external-id',
                'roles'        => 'meta',
                'mappable'     => false,
                'level'        => 'D',
            ],
        'entryDetailRemittanceInformationUnstructuredBlockMessage'                       =>
            [
                'title'        => 'entryDetailRemittanceInformationUnstructuredBlockMessage',
                'default_role' => 'description',
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
                'title'        => 'entryDetailBtcDomainCode',
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
];
