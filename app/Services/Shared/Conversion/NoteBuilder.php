<?php

declare(strict_types=1);

namespace App\Services\Shared\Conversion;

abstract class NoteBuilder
{
    private const string SECTION_HEADER_SIZE = '####';

    private string $notes                    = '';

    public function getNotes(): string
    {
        return $this->notes;
    }

    final protected function renderSection(string $fieldTitle, array $fields): void
    {
        if (!array_any($fields, fn (Field $f) => $f->present())) {
            return;
        }

        $section = '';

        foreach ($fields as $field) {
            $section = $field->renderIfPresent($section);
        }

        if ('' !== $section) {
            $this->notes .= sprintf("%s %s\n%s\n", self::SECTION_HEADER_SIZE, $fieldTitle, $section);
        }
    }

    /**
     * Builds notes on this transaction to give to Firefly III
     */
    abstract public function build(): string;
}
