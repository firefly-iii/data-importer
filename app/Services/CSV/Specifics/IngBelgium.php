<?php
/**
 * IngBelgium.php
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
 * Class IngBelgium.
 *
 * Parses the description and opposing account information (IBAN and name) from CSV files for ING Belgium bank accounts.
 *
 */
class IngBelgium implements SpecificInterface
{
    /**
     * Description of the current specific.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getDescription(): string
    {
        return 'specifics.ingbelgium_descr';
    }

    /**
     * Name of the current specific.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public static function getName(): string
    {
        return 'specifics.ingbelgium_name';
    }

    /**
     * Run the specific code.
     *
     * @param array $row
     *
     * @return array
     *
     */
    public function run(array $row): array
    {
        return self::processTransactionDetails($row);
    }

    /**
     * Gets the description and opposing account information (IBAN and name) from the transaction details and adds
     * them to the row of data.
     *
     * @param array $row
     *
     * @return array the row containing the description and opposing account's IBAN
     */
    protected static function processTransactionDetails(array $row): array
    {
        $transactionDetails = '';
        if (isset($row[9])) {
            $transactionDetails = $row[9];
        }
        if (isset($row[8]) && $transactionDetails == '') {
            $transactionDetails = $row[8];
        }

        $row[11] = self::opposingAccountName($transactionDetails);
        $row[12] = self::opposingAccountIban($transactionDetails);
        $row[13] = self::description($transactionDetails);


        return $row;
    }

    /**
     * Gets the opposing account name from the transaction details.
     *
     * @param string $transactionDetails
     * @return string the opposing account name
     */
    protected static function opposingAccountName(string $transactionDetails): string
    {
        return self::parseInformationFromTransactionDetails($transactionDetails, '/(De|faveur de|Vers|Van):\s*(?<value>.+?)(?=\s{2,})/');

    }

    /**
     * @param string $transactionDetails
     * @param string $regex
     *
     * @return string
     */
    private static function parseInformationFromTransactionDetails(string $transactionDetails, string $regex): string
    {
        if (isset($transactionDetails)) {
            preg_match($regex, $transactionDetails, $matches);
            if (isset($matches['value'])) {
                return trim($matches['value']);
            }
        }

        return '';
    }

    /**
     * Gets the opposing account's IBAN from the transaction details.
     *
     * @param string $transactionDetails
     * @return string the opposing account's IBAN
     */
    protected static function opposingAccountIban(string $transactionDetails): string
    {
        return self::parseInformationFromTransactionDetails($transactionDetails, '/(?<value>[a-zA-Z]{2}\d{14,31})/');
    }

    /**
     * Gets the description from the transaction details and makes sure structured descriptions are in the
     * "+++090/9337/55493+++" format.
     *
     * @param string $transactionDetails
     * @return string the description
     */
    protected static function description(string $transactionDetails): string
    {
        $description = self::parseInformationFromTransactionDetails($transactionDetails, '/(Mededeling|Communication) ?:\s*(?<value>.+)$/');
        return self::convertStructuredDescriptionToProperFormat($description);
    }

    /**
     * @param string $description
     *
     * @return string
     */
    private static function convertStructuredDescriptionToProperFormat(string $description): string
    {
        preg_match('/^\*\*\*(\d{3}\/\d{4}\/\d{5})\*\*\*$/', $description, $matches);
        if (isset($matches[1])) {
            return '+++' . $matches[1] . '+++';
        }
        return $description;
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
        array_push($headers, "opposingAccountName", "opposingAccountIBAN", "description");
        return $headers;
    }
}
