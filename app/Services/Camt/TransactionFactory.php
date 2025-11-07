<?php

declare(strict_types=1);

namespace App\Services\Camt;

use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Camt052\DTO\Statement as Statement052;
use Genkgo\Camt\Camt053\DTO\Statement as Statement053;
use Genkgo\Camt\DTO\Entry;
use InvalidArgumentException;


class TransactionFactory
{
    public static function create(string $camtType, Message $msg, object $levelB, Entry $entry, array $splits): AbstractTransaction
    {
        // Read Config heres        

        if ('052' == $camtType) {
            return new TransactionCamt052($msg, $levelB, $entry, $splits);
        }

        if ('053' == $camtType) {
            return new TransactionCamt053($msg, $levelB, $entry, $splits);
        }
        throw new InvalidArgumentException(
            sprintf('Unhandled CAMT type: "%s". Expected "052" or "053".', $camtType)
        );
    }
}
