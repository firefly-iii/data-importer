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

return [

    // field description for camt.053 import:
    'field_messageId'               => 'Message ID',
    'field_messageCreationDate'     => 'Creation Date',
    'field_messagePageNr'           => 'Page Number',
    'field_statementId'             => 'Statement ID',
    'field_statementAccountIban'    => 'Statement Account IBAN',
    'field_statementAccountIban_description'    => 'If no match, the fallback will be used',
    'field_statementAccountNumber'  => 'Statement Account Number',
    'field_statementCreationDate'  => 'Creation Date',
    'field_entryDate'  => 'Entry Date',
    'field_entryAccountServicerReference'  => 'Accounter Service Reference',
    'field_entryAccountServicerReference_description'  => 'Mostly the public "reference" number',
    'field_entryReference'  => 'Reference',
    'field_entryAdditionalInfo'  => 'Additional Info',
    'field_entryAmount'  => 'Amount',
    'field_entryAmountCurrency'  => 'Currency Code',
    'field_entryValueDate'  => 'Value Date (Interest Calculation date)',
    'field_entryBookingDate'  => 'Booking Date',
    'section_Btc'  => 'Bank Transaction Code',
    'section_opposingPart'  => 'Opposing Part',
    'field_entryBtcDomainCode'  => 'Domain Code',
    'field_entryBtcFamilyCode'  => 'Family Code',
    'field_entryBtcSubFamilyCode'  => 'SubFamily Code',
    'field_entryDetailOpposingAccountIban'  => 'Opposing Account IBAN',
    'field_entryDetailOpposingAccountNumber'  => 'Opposing Account Number',
    'field_entryDetailOpposingName'  => 'Opposing Name',
    'field_entryDetailAmount'  => 'Amount',
    'field_entryDetailAmountCurrency'  => 'Currency Code',
    'section_transaction'  => 'Transaction',
    'field_entryDetailAccountServicerReference'  => 'Accounter Service Reference',
    'field_entryDetailRemittanceInformationUnstructuredBlockMessage'  => 'Unstructured Message',
    'field_entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation'  => 'Structured Message',
];
