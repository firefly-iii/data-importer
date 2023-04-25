<?php

// contains plain-text information as they have to be used for the API or wherever. no objects and stuff

namespace App\Services\Camt;

use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;

class Transaction {

    private $configuration;

    private $levelA;

    private $levelB;

    private $levelC;

    private $levelD;
    private $relatedOppositeParty

    public function __construct($configuration, $_levelA, $_levelB, $_levelC, \Genkgo\Camt\DTO\EntryTransactionDetail $_levelD = null) {

        $this->configuration = $configuration;
        $this->levelA = $levelA;
        $this->levelB = $levelB;
        $this->levelC = $levelC;
        if(null != $_levelD) {
            $this->levelD;
            $this->setOpposingInformations($this->levelD);
        }
    }

    public function getField(string $fieldName = null) {
        $ret = false;
        switch($fieldName) {
            case 'messageId':
                $ret = $this->levelA->getGroupHeader()->getMessageId();
                return $ret
                break;
            /*case 'messageCreationDate':
                $ret = $levelA->
                break;*/
            /*case 'messagePageNr':
                break;*/
            case 'statementId':
                $ret = $this->levelB->getId();
                break;
            case 'statementAccountIban':
                if('Genkgo\Camt\DTO\IbanAccount' == get_class($levelB->getAccount())) {
                    $ret = $this->levelB-->getAccount()->getIdentification();
                }
                return $ret;
                break;
            case 'statementAccountNumber':
                if(in_array(get_class($levelB->getAccount(), array('Genkgo\Camt\DTO\OtherAccount', 'Genkgo\Camt\DTO\ProprietaryAccount', 'Genkgo\Camt\DTO\UPICAccount', 'Genkgo\Camt\DTO\BBANAccount' )))) {
                    $ret = $this->levelB-->getAccount()->getIdentification();
                }
                return $ret;
                break;
            case 'entryDate':
                $ret = $this->levelC->getDate();
                return $ret;
                break;
            case 'entryAccountServicerReference' // external ID
                $ret = $this->levelC->getAccountServicerReference();
                return $ret;
                break;
            case 'entryReference'
                $ret = $this->levelC->getReference();
                return $ret;
                break;
            case 'entryAdditionalInfo':
                $ret = $this->levelC->getAdditionalInfo();
                return $ret;
                break;
            case 'entryAmount':
                $ret = $this->getDecimalAmount($this->levelC->getAmount());;
                return $ret;
                break;
            case 'entryAmountCurrency':
                $ret = $this->levelC->getAmount()->getCurrency()->getCode();
                return $ret;
                break;
/*            case 'entryForeignAmount':
                $ret = $this->getDecimalAmount($this->levelC->getAmount());;
                return $ret;
                break;
            case 'entryForeignAmountCurrency':
                $ret = $this->levelC->getAmount()->getCurrency()->getCode();
                return $ret;
                break;*/
            case 'entryValueDate': // interest calculation date
                $ret = $this->levelC->getValueDate();//->format($this->timeFormat)
                return $ret;
                break;
            case 'entryBookingDate':
                $ret = $this->levelC->getBookingDate();
                return $ret;
                break;
            /*case 'entryTransactionDate': // same as entryBookingDate
                $ret = $this->getField($entryBookingDate);
                return $ret;
                break;*/
            case 'entryBtcDomainCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getCode();
                return $ret;
                break;
            case 'entryBtcFamilyCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
                return $ret;
                break;
            case 'entryBtcSubFamilyCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
                return $ret;
                break;
            case 'entryDetailAccountServicerReference': // external ID
                $ret = $this->levelD->getAccountServicerReference();
                return $ret;
                break;
             case 'entryDetailRemittanceInformationUnstructuredBlockMessage': // unstructured description
                if($remittanceInformation = $this->levelD->getRemittanceInformation()) {
                    if($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
                        $ret = $unstructuredRemittanceInformation->getMessage();
                    }
                }
                return $ret;
                break;
             case 'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation': // structured description
                if($remittanceInformation = $this->levelD->getRemittanceInformation()) {
                    if($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
                        $ret = $unstructuredRemittanceInformation->getMessage());
                    }
                }
                return $ret;
                break;
            case 'entryDetailAmount':
                $ret = $this->getDecimalAmount($this->levelD->getAmount());;
                return $ret;
                break;
            case 'entryDetailAmountCurrency':
                $ret = $this->levelD->getAmount()->getCurrency()->getCode();
                return $ret;
                break;
            case 'entryDetailOpposingAccountIban':
                if($account = $this->relatedParty->getAccount()) {
                     if(get_class($account)) == 'Genkgo\Camt\DTO\IbanAccount') {
                         $ret = $account->getIdentification()
                     }
                }
                return $ret;
                break;
            case 'entryDetailOpposingAccountNumber':
                if($account = $this->relatedParty->getAccount()) {
                     switch(get_class($account)) {
                        case 'Genkgo\Camt\DTO\OtherAccount':
                        case 'Genkgo\Camt\DTO\ProprietaryAccount':
                        case 'Genkgo\Camt\DTO\UPICAccount':
                        case 'Genkgo\Camt\DTO\BBANAccount':
                            $ret = $account->getIdentification();
                        break;
                    }
                }
                return $ret;
                break;
            case 'entryDetailOpposingName':
                if(empty != $this->relatedOppositeParty) {
                    $ret = $this->getOpposingName();
                }
                return $ret;
                break;
            case 'entryDetailBtcDomainCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getCode();
                return $ret;
                break;
            case 'entryDetailBtcFamilyCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
                return $ret;
                break;
            case 'entryDetailBtcSubFamilyCode':
                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
                return $ret;
                break;
        }
    }

    private function setOpposingInformations(\Genkgo\Camt\DTO\EntryTransactionDetail $transactionDetail) { 
        $relatedParties = $transactionDetail->getRelatedParties();
        if($transactionDetail->getAmount()->getAmount() > 0) { // which part in this array is the interessting one?
            $targetRelatedPartyObject = "Genkgo\Camt\DTO\Debtor";
        } else {
            $targetRelatedPartyObject = "Genkgo\Camt\DTO\Creditor";
        }
        foreach($relatedParties as $relatedParty) {
            if(get_class($relatedParty->getRelatedPartyType()) == $targetRelatedPartyObject) {
                $this->relatedOppositeParty = $relatedParty;
                }
            }
        }
    }

    private function getDecimalAmount(\Money\Money $money) {
        $currencies = new ISOCurrencies();
        $moneyDecimalFormatter = new DecimalMoneyFormatter($currencies);
        $decimalAmount = $moneyDecimalFormatter->format($money);
        return($decimalAmount);
    }

    /*public function setBTC(\Genkgo\Camt\DTO\BankTransactionCode $btc) {
        $this->btcDomainCode = $btc->getDomain()->getCode();
        $this->btcFamilyCode = $btc->getDomain()->getFamily()->getCode();
        $this->btcSubFamilyCode = $btc->getDomain()->getFamily()->getSubFamilyCode();
        // TODO get reals Description of Codes
    }*/

    private function getOpposingName(\Genkgo\Camt\DTO\RelatedParty $relatedParty, bool $useEntireAddress = false) { // TODO make depend on configuration
        if(empty($relatedParty->getRelatedPartyType()->getName())) {
            // there is no "name", so use the address instead
            $opposingName = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress());
        } else {
            // there is a name
            $opposingName = $relatedParty->getRelatedPartyType()->getName();
            // but maybe you want also the entire address
            if($useEntireAddress AND $addressLine = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress())) {
                $opposingName .= ', '.$addressLine;
            }
        }
        return $opposingName;
    }

    private function generateAddressLine(\Genkgo\Camt\DTO\Address $address = null) { 
        $addressLines = implode(", ", $address->getAddressLines());
        return $addressLines;
    }
}