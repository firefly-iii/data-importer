<?php

/*
 * ImportedTransactions.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Class ImportedTransactions
 */
class ImportedTransactions
{
    use SerializesModels;

    public const int TEST            = 3;

    public string $configurationFile = '';
    public array $errors             = [];
    public array $messages           = [];
    public array $warnings           = [];
    public array $rateLimits;

    public function __construct(string $configurationFile, array $messages, array $warnings, array $errors, array $rateLimits)
    {
        Log::debug(sprintf('Created event ImportedTransactions("%s") with filtering (2)', $configurationFile));

        // filter messages:
        $this->messages          = $this->filterArray('message(s)', $messages);
        $this->warnings          = $this->filterArray('warning(s)', $warnings);
        $this->errors            = $this->filterArray('error(s)', $errors);
        $this->rateLimits        = $this->filterArray('rate limit message(s)', $rateLimits);
        $this->configurationFile = $configurationFile;
    }

    /**
     * @string $title
     */
    private function filterArray(string $title, array $collection): array
    {
        $count         = 0;
        $newCollection = [];
        foreach ($collection as $index => $set) {
            $newSet = [];
            foreach ($set as $line) {
                $line = (string) $line;
                if ('' !== $line) {
                    $newSet[] = $line;
                    ++$count;
                }
            }
            if (count($newSet) > 0) {
                $newCollection[$index] = $newSet;
            }
        }
        Log::debug(sprintf('Array contains %d %s', $count, $title));

        return $newCollection;
    }
}
