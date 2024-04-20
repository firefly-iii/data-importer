<?php
/*
 * HaveAccess.php
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

namespace App\Console;

use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\SystemInformationRequest;
use GrumpyDictator\FFIIIApiSupport\Response\SystemInformationResponse;

/**
 * Trait HaveAccess
 */
trait HaveAccess
{
    private function haveAccess(): bool
    {
        $url             = (string) config('importer.url');
        $token           = (string) config('importer.access_token');

        // grab token from authentication header.
        $headerToken     = (string) request()->header('Authorization');
        if ('' !== $headerToken) {
            $token = str_replace('Bearer ', '', $headerToken);
            $this->line('Overrule token with token from Authorization header.');
        }

        $this->line(sprintf('Trying to connect to %s...', $url));
        $this->line(sprintf('The last 25 chars of the access token are: %s', substr($token, -25)));

        $request         = new SystemInformationRequest($url, $token);

        $request->setVerify(config('importer.connection.verify'));
        $request->setTimeOut(config('importer.connection.timeout'));

        try {
            /** @var SystemInformationResponse $result */
            $result = $request->get();
        } catch (ApiHttpException $e) {
            $this->error(sprintf('Could not connect to Firefly III at %s: %s', $url, $e->getMessage()));
            $this->error(sprintf('The last 25 chars of the access token are: %s', substr($token, -25)));

            return false;
        }
        $reportedVersion = $result->version;
        if (str_starts_with($reportedVersion, 'v')) {
            $reportedVersion = substr($reportedVersion, 1);
        }
        if (str_starts_with($reportedVersion, 'v')) {
            $this->line(sprintf('Connected to Firefly III v%s', $reportedVersion));
        }
        if (str_starts_with($reportedVersion, 'develop')) {
            $this->line(sprintf('Connected to Firefly III %s', $reportedVersion));
            $this->warn('You are connected to a development version of Firefly III.');
        }

        $compare         = version_compare($reportedVersion, config('importer.minimum_version'));
        if (-1 === $compare && !str_starts_with($reportedVersion, 'develop')) {
            $this->error(sprintf('The data importer cannot communicate with Firefly III v%s. Please upgrade to Firefly III v%s or higher.', $reportedVersion, config('importer.minimum_version')));

            return false;
        }

        return true;
    }

    /**
     * @param null  $verbosity
     * @param mixed $string
     */
    abstract public function error($string, $verbosity = null);

    private function isAllowedPath(string $path): bool
    {
        $error = 'No valid paths in IMPORT_DIR_ALLOWLIST, cannot continue.';
        $paths = config('importer.import_dir_allowlist');
        if (null === $paths) {
            $this->warn($error);

            return false;
        }
        if (is_array($paths) && 0 === count($paths)) {
            $this->warn($error);

            return false;
        }
        if (is_array($paths) && 1 === count($paths) && '' === $paths[0]) {
            $this->warn($error);

            return false;
        }
        foreach ($paths as $current) {
            if ($current === $path) {
                return true;
            }
            if (str_starts_with($path, $current)) {
                app('log')->debug(sprintf('SOFT match on isAllowedPath, "%s" is a subdirectory of "%s"', $path, $current));

                return true;
            }
        }
        app('log')->error(sprintf('"%s" is not in the allowed paths.', $path), $paths);

        return false;
    }
}
