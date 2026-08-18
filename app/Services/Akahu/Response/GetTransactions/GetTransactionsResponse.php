<?php

declare(strict_types=1);

namespace App\Services\Akahu\Response\GetTransactions;


use App\Services\Shared\Response\Response;


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
