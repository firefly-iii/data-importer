<?php

declare(strict_types=1);

namespace App\Services\Camt;

use Genkgo\Camt\Camt052\DTO\Report;
use Genkgo\Camt\DTO\Entry;
use Illuminate\Support\Facades\Log;

class TransactionCamt052 extends AbstractTransaction
{
    public function __construct(
        protected readonly \Genkgo\Camt\DTO\Message $levelA,
        protected readonly Report $levelB,
        protected readonly Entry $levelC,
        protected array $levelD
    ) {
        Log::debug('Constructed a CAMT.052 Transaction');
    }

    /*public function getFieldByIndex(string $field, int $index): string
    {
        // implement 053-specific Logic
        return '';
    }*/
}
