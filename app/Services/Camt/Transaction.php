<?php

// contains plain-text information as they have to be used for the API or wherever. no objects and stuff

namespace App\Services\Camt;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use Genkgo\Camt\Camt053\DTO\Statement;
use Genkgo\Camt\DTO\BBANAccount;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use Genkgo\Camt\DTO\IbanAccount;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\DTO\OtherAccount;
use Genkgo\Camt\DTO\ProprietaryAccount;
use Genkgo\Camt\DTO\RelatedParty;
use Genkgo\Camt\DTO\UPICAccount;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;

class Transaction
{
    public const TIME_FORMAT = 'Y-m-d H:i:s';
    private Configuration $configuration;
    private Message       $levelA;
    private Statement     $levelB;
    private Entry         $levelC;
    private array         $levelD;
    private ?RelatedParty $relatedOppositeParty;


    /**
     * @param Configuration $configuration
     * @param Message       $levelA
     * @param Statement     $levelB
     * @param Entry         $levelC
     * @param array         $levelD
     */
    public function __construct(
        Configuration $configuration, Message $levelA, Statement $levelB, Entry $levelC, array $levelD
    ) {
        $this->relatedOppositeParty = null;
        $this->configuration        = $configuration;
        $this->levelA               = $levelA;
        $this->levelB               = $levelB;
        $this->levelC               = $levelC;
        $this->levelD               = $levelD;
    }

    /**
     * @param string|null $fieldName
     *
     * @return string
     * @throws ImporterErrorException
     */
    public function getField(string $fieldName = null): string
    {
        switch ($fieldName) {
            default:
                throw new ImporterErrorException(sprintf('Unknown field "%s"', $fieldName));
            case 'messageId':
                return (string)$this->levelA->getGroupHeader()->getMessageId();
            case 'statementId':
                return (string)$this->levelB->getId();
            case 'statementAccountIban':
                $ret = '';
                if (IbanAccount::class === get_class($this->levelB->getAccount())) {
                    $ret = $this->levelB->getAccount()->getIdentification();
                }

                return $ret;
            case 'statementAccountNumber':
                $list  = [OtherAccount::class, ProprietaryAccount::class, UPICAccount::class, BBANAccount::class];
                $class = get_class($this->levelB->getAccount());
                $ret   = '';
                if (in_array($class, $list, true)) {
                    $ret = $this->levelB->getAccount()->getIdentification();
                }

                return $ret;
            case 'entryDate':

                return (string)$this->levelC->getValueDate()->format(self::TIME_FORMAT);
            case 'entryAccountServicerReference': // external ID
                // TODO gives a complex object back.
                return (string)$this->levelC->getAccountServicerReference();
            case 'entryReference':
                // TODO gives a complex object back.
                return (string)$this->levelC->getReference();
            case 'entryAdditionalInfo':
                // TODO gives a complex object back.
                return (string)$this->levelC->getAdditionalInfo();
            case 'entryAmount':
                return (string)$this->getDecimalAmount($this->levelC->getAmount());
            case 'entryAmountCurrency':
                return (string)$this->levelC->getAmount()->getCurrency()->getCode();
            case 'entryValueDate':
                // TODO same as entryDate
                return $this->levelC->getValueDate()->format(self::TIME_FORMAT);
            case 'messageCreationDate':
                return (string)$this->levelB->getCreatedOn()->format(self::TIME_FORMAT);
            case 'statementCreationDate':
                // level B
                return (string)$this->levelB->getCreatedOn()->format(self::TIME_FORMAT);
            case 'entryBookingDate':
                return (string)$this->levelC->getBookingDate()->format(self::TIME_FORMAT);
            case 'entryDetailBtcDomainCode':
            case 'entryBtcDomainCode':
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getCode();
            case 'entryDetailBtcFamilyCode':
            case 'entryBtcFamilyCode':
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
            case 'entryDetailBtcSubFamilyCode':
            case 'entryBtcSubFamilyCode':
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
            case 'entryOpposingAccountIban':
            case 'entryDetailOpposingAccountIban':
                $ret = '';
                if ($account = $this->relatedOppositeParty?->getAccount()) {
                    if (get_class($account) === IbanAccount::class) {
                        $ret = (string)$account->getIdentification();
                    }
                }

                return $ret;
            case 'entryOpposingAccountNumber':
            case 'entryDetailOpposingAccountNumber':
                $ret = '';
                if ($account = $this->relatedOppositeParty?->getAccount()) {
                    switch (get_class($account)) {
                        case OtherAccount::class:
                        case ProprietaryAccount::class:
                        case UPICAccount::class:
                        case BBANAccount::class:
                            $ret = $account->getIdentification();
                            break;
                    }
                }

                return $ret;
            case 'entryOpposingName':
            case 'entryDetailOpposingName':
                return '';
                // TODO this code doesnt work yet.
                if (empty ($this->relatedOppositeParty)) {
                    $ret = $this->getOpposingName();
                }

                return $ret;

            case 'entryDetailAmount':
                return (string)$this->getDecimalAmount($this->levelD?->getAmount());
            case 'entryDetailAmountCurrency':
                return (string)$this->levelD?->getAmount()->getCurrency()->getCode();
            case 'entryDetailAccountServicerReference': // external ID
                return (string)$this->levelC?->getAccountServicerReference();
            case 'entryDetailRemittanceInformationUnstructuredBlockMessage': // unstructured description
                $ret = '';
                // TODO assignment in if-statement.
                if ($remittanceInformation = $this->levelD?->getRemittanceInformation()) {
                    if ($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
                        $ret = (string)$unstructuredRemittanceInformation->getMessage();
                    }
                }

                return $ret;
            case 'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation': // structured description
                $ret = '';
                // TODO assignment in if-statement.
                if ($remittanceInformation = $this->levelD?->getRemittanceInformation()) {
                    if ($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
                        $ret = (string)$unstructuredRemittanceInformation->getMessage();
                    }
                }

                return $ret;
                break;

            //            case 'entryBookingDate':
            //                $ret = $this->levelC->getBookingDate();
            //
            //                return $ret;
            //                break;
            //            /*case 'entryTransactionDate': // same as entryBookingDate
            //                $ret = $this->getField($entryBookingDate);
            //                return $ret;
            //                break;*/
            //            case 'entryBtcDomainCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryBtcFamilyCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryBtcSubFamilyCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailAccountServicerReference': // external ID
            //                $ret = $this->levelD->getAccountServicerReference();
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailRemittanceInformationUnstructuredBlockMessage': // unstructured description
            //                if ($remittanceInformation = $this->levelD->getRemittanceInformation()) {
            //                    if ($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
            //                        $ret = $unstructuredRemittanceInformation->getMessage();
            //                    }
            //                }
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation': // structured description
            //                if ($remittanceInformation = $this->levelD->getRemittanceInformation()) {
            //                    if ($unstructuredRemittanceInformation = $remittanceInformation->getUnstructuredBlock()) {
            //                        $ret = $unstructuredRemittanceInformation->getMessage();
            //                    }
            //                }
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailAmount':
            //                $ret = $this->getDecimalAmount($this->levelD->getAmount());;
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailAmountCurrency':
            //                $ret = $this->levelD->getAmount()->getCurrency()->getCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailOpposingAccountIban':
            //                if ($account = $this->relatedParty->getAccount()) {
            //                    if (get_class($account) === 'Genkgo\Camt\DTO\IbanAccount') {
            //                        $ret = $account->getIdentification();
            //                    }
            //                }
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailOpposingAccountNumber':
            //                if ($account = $this->relatedParty->getAccount()) {
            //                    switch (get_class($account)) {
            //                        case 'Genkgo\Camt\DTO\OtherAccount':
            //                        case 'Genkgo\Camt\DTO\ProprietaryAccount':
            //                        case 'Genkgo\Camt\DTO\UPICAccount':
            //                        case 'Genkgo\Camt\DTO\BBANAccount':
            //                            $ret = $account->getIdentification();
            //                            break;
            //                    }
            //                }
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailOpposingName':
            //                if (empty ($this->relatedOppositeParty)) {
            //                    $ret = $this->getOpposingName();
            //                }
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailBtcDomainCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailBtcFamilyCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
            //
            //                return $ret;
            //                break;
            //            case 'entryDetailBtcSubFamilyCode':
            //                $ret = $this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
            //
            //                return $ret;
            //                break;
            /**
             * Unused entries:
             */

            //                 break;
            //            /*            case 'entryForeignAmount':
            //                            $ret = $this->getDecimalAmount($this->levelC->getAmount());;
            //                            return $ret;
            //                            break;
            //                        case 'entryForeignAmountCurrency':
            //                            $ret = $this->levelC->getAmount()->getCurrency()->getCode();
            //                            return $ret;
            //                            break;*/
        }
    }

    /**
     * @param EntryTransactionDetail $transactionDetail
     *
     * @return void
     */
    private function setOpposingInformation(EntryTransactionDetail $transactionDetail)
    {
        $relatedParties = $transactionDetail->getRelatedParties();
        if ($transactionDetail->getAmount()->getAmount() > 0) { // which part in this array is the interessting one?
            $targetRelatedPartyObject = "Genkgo\Camt\DTO\Debtor";
        } else {
            $targetRelatedPartyObject = "Genkgo\Camt\DTO\Creditor";
        }
        foreach ($relatedParties as $relatedParty) {
            if (get_class($relatedParty->getRelatedPartyType()) == $targetRelatedPartyObject) {
                $this->relatedOppositeParty = $relatedParty;
            }
        }
    }

    private function getDecimalAmount(?Money $money): string
    {
        if (null === $money) {
            return '';
        }
        $currencies            = new ISOCurrencies();
        $moneyDecimalFormatter = new DecimalMoneyFormatter($currencies);

        return $moneyDecimalFormatter->format($money);
    }

    /*public function setBTC(\Genkgo\Camt\DTO\BankTransactionCode $btc) {
        $this->btcDomainCode = $btc->getDomain()->getCode();
        $this->btcFamilyCode = $btc->getDomain()->getFamily()->getCode();
        $this->btcSubFamilyCode = $btc->getDomain()->getFamily()->getSubFamilyCode();
        // TODO get reals Description of Codes
    }*/

    private function getOpposingName(\Genkgo\Camt\DTO\RelatedParty $relatedParty, bool $useEntireAddress = false)
    { // TODO make depend on configuration
        if (empty($relatedParty->getRelatedPartyType()->getName())) {
            // there is no "name", so use the address instead
            $opposingName = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress());
        } else {
            // there is a name
            $opposingName = $relatedParty->getRelatedPartyType()->getName();
            // but maybe you want also the entire address
            if ($useEntireAddress and $addressLine = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress())) {
                $opposingName .= ', ' . $addressLine;
            }
        }

        return $opposingName;
    }

    private function generateAddressLine(\Genkgo\Camt\DTO\Address $address = null)
    {
        $addressLines = implode(", ", $address->getAddressLines());

        return $addressLines;
    }

    /**
     * @return int
     */
    public function countSplits(): int
    {
        return count($this->levelD);
    }

    /**
     * @param int $index
     *
     * @return string
     */
    public function getDate(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->levelC->getValueDate()->format(self::TIME_FORMAT);
    }

    /**
     * @param int $index
     *
     * @return string
     */
    public function getCurrencyCode(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->levelC->getAmount()->getCurrency()->getCode();
    }
    /**
     * @param int $index
     *
     * @return string
     */
    public function getAmount(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->getDecimalAmount($this->levelC->getAmount());
    }
}
