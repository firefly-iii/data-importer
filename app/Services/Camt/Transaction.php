<?php

// contains plain-text information as they have to be used for the API or wherever. no objects and stuff

namespace App\Services\Camt;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use Genkgo\Camt\Camt053\DTO\Statement;
use Genkgo\Camt\DTO\Address;
use Genkgo\Camt\DTO\BBANAccount;
use Genkgo\Camt\DTO\Creditor;
use Genkgo\Camt\DTO\Debtor;
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


    /**
     * @param  Configuration  $configuration
     * @param  Message  $levelA
     * @param  Statement  $levelB
     * @param  Entry  $levelC
     * @param  array  $levelD
     */
    public function __construct(
        Configuration $configuration,
        Message $levelA,
        Statement $levelB,
        Entry $levelC,
        array $levelD
    ) {
        $this->configuration = $configuration;
        $this->levelA        = $levelA;
        $this->levelB        = $levelB;
        $this->levelC        = $levelC;
        $this->levelD        = $levelD;
    }

    /**
     * @return int
     */
    public function countSplits(): int
    {
        return count($this->levelD);
    }

    /**
     * @param  int  $index
     *
     * @return string
     */
    public function getCurrencyCode(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->levelC->getAmount()->getCurrency()->getCode();
    }

    /**
     * @param  int  $index
     *
     * @return string
     */
    public function getDate(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->levelC->getValueDate()->format(self::TIME_FORMAT);
    }

    /**
     * @param  string  $field
     * @param  int  $index
     *
     * @return string
     * @throws ImporterErrorException
     */
    public function getFieldByIndex(string $field, int $index): string
    {
        switch ($field) {
            default:
                // temporary debug message:
//                echo sprintf('Unknown field "%s" in getFieldByIndex(%d)', $field, $index);
//                echo PHP_EOL;
//                exit;
                // end temporary debug message
                throw new ImporterErrorException(sprintf('Unknown field "%s" in getFieldByIndex(%d)', $field, $index));

            // LEVEL A
            case 'messageId':
                // always the same, since its level A.
                return (string)$this->levelA->getGroupHeader()->getMessageId();

            // LEVEL B
            case 'statementId':
                // always the same, since its level B.
                return (string)$this->levelB->getId();
            case 'statementCreationDate':
                // always the same, since its level B.
                return (string)$this->levelB->getCreatedOn()->format(self::TIME_FORMAT);
            case 'statementAccountIban':
                // always the same, since its level B.
                $ret = '';
                if (IbanAccount::class === get_class($this->levelB->getAccount())) {
                    $ret = $this->levelB->getAccount()->getIdentification();
                }

                return $ret;
            case 'statementAccountNumber':
                // always the same, since its level B.
                $list  = [OtherAccount::class, ProprietaryAccount::class, UPICAccount::class, BBANAccount::class];
                $class = get_class($this->levelB->getAccount());
                $ret   = '';
                if (in_array($class, $list, true)) {
                    $ret = $this->levelB->getAccount()->getIdentification();
                }

                // LEVEL C
                return $ret;
            case 'entryAccountServicerReference':
                // always the same, since its level C.
                return (string)$this->levelC->getAccountServicerReference();
            case 'entryReference':
                // always the same, since its level C.
                return (string)$this->levelC->getReference();
            case 'entryAdditionalInfo':
                // always the same, since its level C.
                return (string)$this->levelC->getAdditionalInfo();
            case 'entryAmount':
                // always the same, since its level C.
                return (string)$this->getDecimalAmount($this->levelC->getAmount());
            case 'entryAmountCurrency':
                // always the same, since its level C.
                return (string)$this->levelC->getAmount()->getCurrency()->getCode();
            case 'entryValueDate':
                // always the same, since its level C.
                return (string)$this->levelC->getValueDate()->format(self::TIME_FORMAT);
            case 'entryBookingDate':
                // always the same, since its level C.
                return (string)$this->levelC->getBookingDate()->format(self::TIME_FORMAT);
            case 'entryBtcDomainCode':
                // always the same, since its level C.
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getCode();
            case 'entryBtcFamilyCode':
                // always the same, since its level C.
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
            case 'entryBtcSubFamilyCode':
                // always the same, since its level C.
                return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();

            // LEVEL D
            case 'entryDetailAccountServicerReference':
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return '';
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return (string)$info->getReference()->getAccountServicerReference();
            case 'entryDetailRemittanceInformationUnstructuredBlockMessage':
                $result = '';

                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    // TODO return nothing?
                    return $result;
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                if (null !== $info->getRemittanceInformation() && null !== $info->getRemittanceInformation()->getUnstructuredBlock()) {
                    $result .= (string)$info->getRemittanceInformation()->getUnstructuredBlock()->getMessage();
                }

                return $result;
            case 'entryDetailRemittanceInformationStructuredBlockAdditionalRemittanceInformation':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    // TODO return nothing?
                    return '';
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index]; // TODO, check if always readable or if we need some checks like with "unstructuredBlockMessage"

                return (string)$info->getRemittanceInformation()?->getStructuredBlock()?->getAdditionalRemittanceInformation();
            case 'entryDetailAmount':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return ''; // config.-depending fallback handled in mapping
                    //return $this->getDecimalAmount($this->levelC->getAmount());
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return $this->getDecimalAmount($info->getAmount());
            case 'entryDetailAmountCurrency':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return ''; // config.-depending fallback handled in mapping
                    //return (string)$this->levelC->getAmount()->getCurrency()->getCode();
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return (string)$info->getAmount()->getCurrency()->getCode();
            case 'entryDetailBtcDomainCode':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    //return (string)$this->levelC->getBankTransactionCode()->getDomain()->getCode();
                    return ''; // config.-depending fallback handled in mapping
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return (string)$info->getBankTransactionCode()->getDomain()->getCode();
            case 'entryDetailBtcFamilyCode':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    //return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getCode();
                    return ''; // config.-depending fallback handled in mapping
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return (string)$info->getBankTransactionCode()->getDomain()->getFamily()->getCode();
            case 'entryDetailBtcSubFamilyCode':
                // this is level D, so grab from level C or loop.
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    //return (string)$this->levelC->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
                    return ''; // config.-depending fallback handled in mapping
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];

                return (string)$info->getBankTransactionCode()->getDomain()->getFamily()->getSubFamilyCode();
            case 'entryDetailOpposingAccountIban':
                $result = '';

                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return $result;
                }
                /** @var EntryTransactionDetail $info */
                $info            = $this->levelD[$index];
                $opposingAccount = $this->getOpposingParty($info)?->getAccount();
                if (IbanAccount::class === get_class($opposingAccount)) {
                    $result = (string)$opposingAccount->getIdentification();
                }

                return $result;
            case 'entryDetailOpposingAccountNumber':
                $result = '';

                $list = [OtherAccount::class, ProprietaryAccount::class, UPICAccount::class, BBANAccount::class];
                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return $result;
                }
                /** @var EntryTransactionDetail $info */
                $info            = $this->levelD[$index];
                $opposingAccount = $this->getOpposingParty($info)?->getAccount();
                $class           = null !== $opposingAccount ? get_class($opposingAccount) : '';
                if (in_array($class, $list, true)) {
                    $result = (string)$opposingAccount->getIdentification();
                }

                return $result;
            case 'entryDetailOpposingName':
                $result = '';

                if (0 === count($this->levelD) || !array_key_exists($index, $this->levelD)) {
                    return $result;
                }
                /** @var EntryTransactionDetail $info */
                $info = $this->levelD[$index];
                //$result  = '';
                $opposingParty = $this->getOpposingParty($info);
                $result        = $this->getOpposingName($opposingParty);

                return $result;
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

    /**
     * @param  int  $index
     *
     * @return string
     */
    public function getAmount(int $index): string
    {
        // TODO loop level D for the date that belongs to the index
        return (string)$this->getDecimalAmount($this->levelC->getAmount());
    }

    private function getOpposingName(RelatedParty $relatedParty, bool $useEntireAddress = false): string {
        $opposingName = '';
        // TODO make depend on configuration
        if ('' === (string)$relatedParty->getRelatedPartyType()->getName()) {
            // there is no "name", so use the address instead
            $opposingName = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress());
        }
        if ('' !== (string)$relatedParty->getRelatedPartyType()->getName()) {
            // there is a name
            $opposingName = $relatedParty->getRelatedPartyType()->getName();
            // but maybe you want also the entire address
            if ($useEntireAddress and $addressLine = $this->generateAddressLine($relatedParty->getRelatedPartyType()->getAddress())) {
                $opposingName .= ', '.$addressLine;
            }
        }

        return $opposingName;
    }

    private function generateAddressLine(Address $address = null) {
        $addressLines = implode(", ", $address->getAddressLines());

        return $addressLines;
    }

    /**
     * @param  EntryTransactionDetail  $transactionDetail
     */
    private function getOpposingParty(EntryTransactionDetail $transactionDetail): Creditor|Debtor|null
    {
        $relatedParties           = $transactionDetail->getRelatedParties();
        $targetRelatedPartyObject = "Genkgo\Camt\DTO\Creditor";
        if ($transactionDetail->getAmount()->getAmount() > 0) { // which part in this array is the interesting one?
            $targetRelatedPartyObject = "Genkgo\Camt\DTO\Debtor";
        }
        foreach ($relatedParties as $relatedParty) {
            if (get_class($relatedParty->getRelatedPartyType()) == $targetRelatedPartyObject) {
                return $relatedParty;
            }
        }
        return null;
    }
}
