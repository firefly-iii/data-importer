<?php
/*
 * CombinedProgressInformation.php
 * Copyright (c) 2023 james@firefly-iii.org
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

namespace App\Services\Shared\Conversion;

trait CombinedProgressInformation
{
    private array                      $allErrors;
    private array                      $allMessages;
    private array                      $allWarnings;

    /**
     * @return array
     */
    final public function getAllErrors(): array
    {
        return $this->allErrors;
    }

    /**
     * @return array
     */
    final public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    /**
     * @return array
     */
    final public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    /**
     * @param array $collection
     * @param int   $count
     *
     * @return array
     */
    final protected function mergeArrays(array $collection, int $count): array
    {
        $return = [];
        foreach ($collection as $set) {
            if (0 === count($set)) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                if (array_key_exists($i, $set)) {
                    $return[$i] = array_key_exists($i, $return) ? $return[$i] : [];
                    $return[$i] = array_merge($return[$i], $set[$i]);
                }
            }
        }

        // sanity check (should not be necessary)
        foreach ($return as $index => $set) {
            if (0 === count($set)) {
                unset($return[$index]);
            }
        }
        if (0 === count($return)) {
            $return = [];
        }

        return $return;
    }
}
