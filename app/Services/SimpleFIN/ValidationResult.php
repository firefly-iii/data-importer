<?php
/*
 * ValidationResult.php
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

namespace App\Services\SimpleFIN;

/**
 * Validation result container
 */
readonly class ValidationResult
{
    public function __construct(
        private bool  $isValid,
        private array $errors = [],
        private array $warnings = []
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    public function getErrorMessages(): array
    {
        return array_map(fn ($error) => $error['message'], $this->errors);
    }

    public function getWarningMessages(): array
    {
        return array_map(fn ($warning) => $warning['message'], $this->warnings);
    }

    public function toArray(): array
    {
        return [
            'valid'    => $this->isValid,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
