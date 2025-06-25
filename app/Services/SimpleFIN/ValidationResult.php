<?php

declare(strict_types=1);

namespace App\Services\SimpleFIN;

/**
 * Validation result container
 */
class ValidationResult
{
    public function __construct(
        private bool $isValid,
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
