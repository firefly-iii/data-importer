<?php

/*
 * GeneratesIdentifier.php
 * Copyright (c) 2025 james@firefly-iii.org
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

namespace App\Services\Shared\Submission;

use Illuminate\Support\Facades\Log;
use Storage;
use Str;

/**
 * Trait GeneratesIdentifier
 */
trait GeneratesIdentifier
{
    protected string $identifier;
    private string   $diskName = 'submission-routines';

    public function generateIdentifier(): string
    {
        Log::debug('Going to generate submission routine identifier.');
        $disk             = Storage::disk($this->diskName);
        $count            = 0;
        do {
            $generatedId = sprintf('submission-%s', Str::random(12));
            ++$count;
            Log::debug(sprintf('Attempt #%d results in "%s"', $count, $generatedId));
        } while ($count < 30 && $disk->exists($generatedId));
        $this->identifier = $generatedId;
        Log::info(sprintf('Job identifier is "%s"', $generatedId));

        return $generatedId;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
