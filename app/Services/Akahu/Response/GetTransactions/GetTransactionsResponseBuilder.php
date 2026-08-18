<?php

declare(strict_types=1);

namespace App\Services\Akahu\Response\GetTransactions;

use App\Exceptions\ImporterErrorException;
use App\Services\Akahu\Model\Transaction\Transaction;
use Illuminate\Support\Facades\Log;

final class GetTransactionsResponseBuilder
{
    public array $transactions = [];

    public function submitPageAndGetNextCursor(array $json): ?string
    {
        if (array_key_exists('success', $json)) {
            if (!$json['success']) {
                $msg = 'Akahu api returned a success value of "false". See logs for more details';
                Log::error($msg.' json: "'.json_encode($json).'"');

                throw new ImporterErrorException($msg);
            }

            Log::debug('Akahu GetTransactionsRequest returned successfully');

            if (array_key_exists('items', $json)) {
                $this->submitPage($json['items']);
            }

            if (array_key_exists('cursor', $json) && array_key_exists('next', $json['cursor'])) {
                $nextCursor = $json['cursor']['next'];

                Log::debug(sprintf('Akahu api returned a page with a next cursor: %s', $nextCursor));

                return $nextCursor;
            }

            return null;
        }

        $msg = 'Akahu api returned badly structured json, expected response to contain';
        $msg .= ' a "success" attribute and an "items" attribute. See logs for more details.';
        Log::error($msg.' json: "'.json_encode($json).'"');

        throw new ImporterErrorException($msg);
    }

    private function submitPage(array $transactionsJson): void
    {
        $pageTransactions = array_map(Transaction::fromJson(...), $transactionsJson);
        array_push($this->transactions, ...$pageTransactions);
    }

    public function build(): GetTransactionsResponse
    {
        return new GetTransactionsResponse($this->transactions);
    }
}
