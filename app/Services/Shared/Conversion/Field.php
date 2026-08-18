<?php

declare(strict_types=1);

namespace App\Services\Shared\Conversion;

final class Field
{
    private ?string $fieldName = null;
    private ?string $field     = null;

    public function __construct(?string $fieldName, ?string $field)
    {
        $this->fieldName = $fieldName;
        $this->field     = $field;
    }

    public function renderIfPresent(string $notes): string
    {
        if ($this->present()) {
            $notes .= sprintf(" - %s: %s\n", $this->fieldName, $this->field);
        }

        return $notes;
    }

    public function present(): bool
    {
        return null !== $this->field;
    }
}
