<?php
/**
 * Belfius.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\CSV\Specifics;

/**
 * Class Belfius.
 *
 * Fixes Belfius CSV files to:
 *  - Correct descriptions for recurring transactions so doubles can be detected when the equivalent incoming
 *    transaction is imported.
 *
 */
class Belfius implements SpecificInterface
{
    /**
     * Description of this specific fix.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getDescription(): string
    {
        return 'specifics.belfius_descr';
    }

    /**
     * Name of specific fix.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getName(): string
    {
        return 'specifics.belfius_name';
    }

    /**
     * Run the fix.
     *
     * @param array $row
     *
     * @return array
     *
     */
    public function run(array $row): array
    {
        return self::processRecurringTransactionDescription($row);
    }

    /**
     * Fixes the description for outgoing recurring transactions so doubles can be detected when the equivalent incoming
     * transaction is imported for another bank account.
     *
     * @param array $row
     *
     * @return array the row containing the new description
     */
    protected static function processRecurringTransactionDescription(array $row): array
    {
        if (!isset($row[5], $row[14])) {
            return $row;
        }

        $opposingAccountName = $row[5];
        /** @noinspection MultiAssignmentUsageInspection */
        $description = $row[14];

        preg_match('/DOORLOPENDE OPDRACHT.*\s+' . preg_quote($opposingAccountName, '/') . '\s+(.+)\s+REF.\s*:/', $description, $matches);

        if (isset($matches[1])) {
            $row[14] = $matches[1];
        }

        return $row;
    }

    /**
     * If the fix(es) in your file add or remove columns from the CSV content, this must be reflected on the header row
     * as well.
     *
     * @param array $headers
     *
     * @return array
     */
    public function runOnHeaders(array $headers): array
    {
        return $headers;
    }
}
