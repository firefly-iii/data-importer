<?php

declare(strict_types=1);

namespace App\Services\Akahu\Response\GetTransactions;

use App\Services\Akahu\Model\Account\Account;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;

final class GetTransactionsResponse extends Response
{
    private array $transactions;

    public function __construct(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }
}
