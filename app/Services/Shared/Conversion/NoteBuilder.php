<?php

/*
 * NoteBuilder.php
 * Copyright (c) 2026 james@firefly-iii.org
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
