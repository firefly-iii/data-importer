<?php

declare(strict_types=1);

namespace App\Services\Akahu\Conversion;


use App\Services\Akahu\Model\Transaction\Transaction;
 use App\Support\Http\CollectsAccounts;
use App\Support\Internal\DuplicateSafetyCatch;

use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use Illuminate\Support\Facades\Log;
use App\Services\Shared\Authentication\SecretManager;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use BcMath\Number;
use App\Support\Facades\Steam;

final class TransactionConverter
{
    use CollectsAccounts;
    use DuplicateSafetyCatch;

    private TransactionFetcher $transactionFetcher;
    private array $targetAccounts;
    // private int $accountDecimalPlaces;

    public function __construct(TransactionFetcher $transactionFetcher)
    {
        $this->transactionFetcher = $transactionFetcher;

        $fireflyAccountId = $transactionFetcher->getFireflyAccountId();
        // $this->accountDecimalPlaces = $this->getAccountDecimalPlaces($fireflyAccountId);
    }

    public function convert(): array
    {
        $this->targetAccounts = $this->collectAllTargetAccounts();
        $fireflyAccountId = $this->transactionFetcher->getFireflyAccountId();
        $akahuTransactions = $this->transactionFetcher->fetch();

        $result = [];
        $total = count($akahuTransactions);

        foreach ($akahuTransactions as $index => $akahuTransaction) {
            Log::debug(sprintf('[%d/%d] Converting transaction', $index + 1, $total));
            $result[] = $this->convertTransaction($akahuTransaction);
            Log::debug(sprintf('[%d/%d] Done converting transaction.', $index + 1, $total));
        }

        return $result;
    }

    public function getAccountDecimalPlaces(int $fireflyAccountId): ?int
    {
        $token = SecretManager::getAccessToken();
        $baseUrl = SecretManager::getBaseUrl();

        $request = new GetAccountRequest($baseUrl, $token);
        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));
        $request->setId($fireflyAccountId);

        try {
            $result = $request->get();
        } catch (ApiHttpException $e) {
            Log::error('Could not get Firefly III account.');
            Log::debug($e->getMessage());

            return null;
        }

        return $result->getAccount()->currencyDecimalPlaces;
    }

    private function convertTransaction(Transaction $akahuTransaction): array
    {
        $configuration = $this->transactionFetcher->getImportJob()->getConfiguration();
        $noteBuilder = new AkahuNoteBuilder($akahuTransaction);

        $fireflyRequest = [
            'apply_rules'             => $configuration->isRules(),
            'fire_webhooks'           => $configuration->isWebhooks(),
            'error_if_duplicate_hash' => $configuration->isIgnoreDuplicateTransactions(),
            'transactions'            => [],
        ];

        $fireflyTransaction = [
            'date'                   => $akahuTransaction->getDate()->toRfc3339String(),
            'amount'                 => (string) $akahuTransaction->getAmount(),
            'description'            => $akahuTransaction->getDescription(),
            'order'                  => 0,
            'notes'                  => $noteBuilder->build(),
        ];

        $conversion = $akahuTransaction->getMeta()?->getConversion();
        if (!is_null($conversion)) {
            $amount = $conversion?->getAmount();
            $currency = $conversion?->getCurrency();

            if (!is_null($amount) && !is_null($currency)) {
                $fireflyTransaction['foreign_amount'] = Steam::bcstringify($amount, 2);
                $fireflyTransaction['foreign_currency_code'] = $currency;
            }
        }

        $akahuId = $akahuTransaction->getAkahuId();
        if (!is_null($akahuId)) {
            $fireflyTransaction['external_id'] = $akahuId;
        }

        $zero = new Number('0');
        if ($akahuTransaction->getAmount()->compare($zero) >= 0) {
            Log::debug('Amount is positive or zero: assume transfer or deposit');
            $fireflyTransaction = $this->appendDepositInfo($fireflyTransaction, $akahuTransaction);
        }
        if ($akahuTransaction->getAmount()->compare($zero) < 0) {
            Log::debug('Amount is negative: assume transfer or withdrawal');
            $fireflyTransaction = $this->appendWithdrawalInfo($fireflyTransaction, $akahuTransaction);
        }

        $fireflyRequest['transactions'][] = $fireflyTransaction;
        return $fireflyRequest;
    }

    private function appendDepositInfo(array $fireflyTransaction, Transaction $akahuTransaction): array
    {
        $fireflyTransaction['type'] = 'deposit';
        // FIXME: pull dp from api
        $fireflyTransaction['amount'] = Steam::bcstringify($akahuTransaction->getAmount(), 2);
        $fireflyTransaction['destination_id'] = $this->transactionFetcher->getFireflyAccountId();
        $fireflyTransaction['source_name'] = '(unknown source)';

        $sourceBban = $akahuTransaction->getMeta()?->getOtherAccount();

        if (!is_null($sourceBban)) {
            $fireflyTransaction['source_name'] = $sourceBban;
        }

        return $fireflyTransaction;
    }

    private function appendWithdrawalInfo(array $fireflyTransaction, Transaction $akahuTransaction): array
    {
        $fireflyTransaction['type'] = 'withdrawal';
        $amount = $akahuTransaction->getAmount() * new Number('-1');
        // FIXME: pull dp from api
        $fireflyTransaction['amount'] = Steam::bcstringify($amount, 2);
        $fireflyTransaction['source_id'] = $this->transactionFetcher->getFireflyAccountId();
        $fireflyTransaction['destination_name'] = '(unknown source)';

        $destinationBban = $akahuTransaction->getMeta()?->getOtherAccount();
        $destinationMerchantName = $akahuTransaction->getMerchant()?->getName();

        if (!is_null($destinationMerchantName)) {
            $fireflyTransaction['destination_name'] = $destinationMerchantName;
        }

        if (!is_null($destinationBban)) {
            $fireflyTransaction['destination_name'] = $destinationBban;
        }

        return $fireflyTransaction;
    }
}
