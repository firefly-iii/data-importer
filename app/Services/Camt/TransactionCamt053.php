<?php

declare(strict_types=1);

namespace App\Services\Camt;

use Genkgo\Camt\Camt053\DTO\Statement;
use Genkgo\Camt\DTO\Entry;
use Illuminate\Support\Facades\Log;

class TransactionCamt053 extends AbstractTransaction
{
    public function __construct(
        protected readonly \Genkgo\Camt\DTO\Message $levelA,
        protected readonly Statement $levelB,
        protected readonly Entry $levelC,
        protected array $levelD
    ) {
        Log::debug('Constructed a CAMT.053 Transaction');
    }

    /*public function getFieldByIndex(string $field, int $index): string
    {
        // implement 053-specific Logic
        return '';
    }*/
}
