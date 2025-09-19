<?php

/*
 * VerifyJSON.php
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

use Illuminate\Support\Facades\Log;
use JsonException;
use Swaggest\JsonSchema\Exception;
use Swaggest\JsonSchema\Schema;

/**
 * Trait VerifyJSON
 */
trait VerifyJSON
{
    protected string $errorMessage = '';

    private function verifyJSON(string $file): bool
    {
        // basic check on the JSON.
        $json       = (string)file_get_contents($file);

        try {
            $config = json_decode($json, null, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $message            = sprintf('The importer can\'t import: could not decode the JSON in the config file: %s', $e->getMessage());
            Log::error($message);
            $this->errorMessage = $message;

            return false;
        }
        // validate JSON schema.
        $schemaFile = resource_path('schemas/v3.json');
        if (!file_exists($schemaFile)) {
            $message            = sprintf('The schema file "%s" does not exist.', $schemaFile);
            Log::error($message);
            $this->errorMessage = $message;

            return false;
        }
        $schema     = json_decode(file_get_contents($schemaFile));

        try {
            Schema::import($schema)->in($config);
        } catch (Exception|\Exception $e) {
            $message            = sprintf('Configuration file "%s" does not adhere to the v3 schema: %s', $file, $e->getMessage());

            Log::error($message);
            $this->errorMessage = $message;

            return false;
        }

        return true;
    }
}
