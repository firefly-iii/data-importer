<?php

/*
 * Field.php
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
