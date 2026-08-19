<?php


/*
 * TransactionFactory.php
 * Copyright (c) 2026 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Camt;

use Genkgo\Camt\Camt052\DTO\Report;
use Genkgo\Camt\Camt053\DTO\Statement;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\Message;
use InvalidArgumentException;

final class TransactionFactory
{
    public static function create(string $camtType, Message $msg, Report|Statement $levelB, Entry $entry, array $splits): AbstractTransaction
    {
        // Read Config heres

        if ('052' === $camtType) {
            return new TransactionCamt052($msg, $levelB, $entry, $splits);
        }

        if ('053' === $camtType) {
            return new TransactionCamt053($msg, $levelB, $entry, $splits);
        }

        throw new InvalidArgumentException(sprintf('Unhandled CAMT type: "%s". Expected "052" or "053".', $camtType));
    }
}
