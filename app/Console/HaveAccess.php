<?php
declare(strict_types=1);
/**
 * HaveAccess.php
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

namespace App\Console;

use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\SystemInformationRequest;

/**
 * Trait HaveAccess
 */
trait HaveAccess
{
    /**
     * @return bool
     */
    private function haveAccess(): bool
    {
        $url     = (string) config('importer.url');
        $token   = (string) config('importer.access_token');
        $request = new SystemInformationRequest($url, $token);

        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            $request->get();
        } catch (ApiHttpException $e) {
            $this->error(sprintf('Could not connect to Firefly III: %s', $e->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * @param      $string
     * @param null $verbosity
     *
     * @return void
     */
    abstract public function error($string, $verbosity = null);
}
