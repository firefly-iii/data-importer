<?php

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
