<?php

declare(strict_types=1);

namespace App\Services\Camt;

use Genkgo\Camt\Camt053\DTO\Statement;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\Message;
use Illuminate\Support\Facades\Log;

class TransactionCamt053 extends AbstractTransaction
{
    public function __construct(Message $levelA, Statement $levelB, Entry $levelC, array $levelD)
    {
        $this->levelA = $levelA;
        $this->levelB = $levelB;
        $this->levelC = $levelC;
        $this->levelD = $levelD;

        Log::debug('Constructed a CAMT.053 Transaction');
    }

    /*public function getFieldByIndex(string $field, int $index): string
     * {
     * // implement 053-specific Logic
     * return '';
     * }*/
}
