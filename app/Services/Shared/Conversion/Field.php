<?php

declare(strict_types=1);

namespace App\Services\Shared\Conversion;

final class Field
{
    private ?string $fieldName;
    private $field;

    public function __construct(string $fieldName, $field)
    {
        $this->fieldName = $fieldName;
        $this->field = $field;
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
        return !is_null($this->field);
    }
}
