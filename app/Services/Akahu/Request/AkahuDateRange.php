<?php

/*
 * AkahuDateRange.php
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

namespace App\Services\Akahu\Request;

use App\Services\Shared\Configuration\Configuration;
use Carbon\Carbon;

final class AkahuDateRange
{
    private ?Carbon $dateNotBefore;
    private ?Carbon $dateNotAfter;

    public function __construct(Configuration $configuration)
    {
        $tz                  = config('app.timezone');
        $dateNotBefore       = null;
        $dateNotAfter        = null;

        if ('' !== $configuration->getDateNotBefore()) {
            $dateNotBefore = Carbon::parse($configuration->getDateNotBefore(), $tz);
        }

        if ('' !== $configuration->getDateNotAfter()) {
            $dateNotAfter = Carbon::parse($configuration->getDateNotAfter(), $tz);
        }

        $this->dateNotBefore = $dateNotBefore;
        $this->dateNotAfter  = $dateNotAfter;
    }

    public function startDate(): ?Carbon
    {
        // https://developers.akahu.nz/docs/accessing-transactional-data#getting-a-date-range
        // We want to include transactions that fall *on* the `dateNotBefore`
        // date as well.
        return $this->dateNotBefore?->copy()?->subMillisecond();
    }

    public function endDate(): ?Carbon
    {
        // The `end` query parameter is inclusive.
        return $this->dateNotAfter?->copy()?->addDay();
    }
}
