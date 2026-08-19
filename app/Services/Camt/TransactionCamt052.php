<?php


/*
 * TransactionCamt052.php
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
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\Message;
use Illuminate\Support\Facades\Log;

final class TransactionCamt052 extends AbstractTransaction
{
    public function __construct(Message $levelA, Report $levelB, Entry $levelC, array $levelD)
    {
        $this->levelA = $levelA;
        $this->levelB = $levelB;
        $this->levelC = $levelC;
        $this->levelD = $levelD;
        Log::debug('Constructed a CAMT.052 Transaction');
    }

    /*public function getFieldByIndex(string $field, int $index): string
     * {
     * // implement 053-specific Logic
     * return '';
     * }*/
}
