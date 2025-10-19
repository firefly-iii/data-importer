<?php

/*
 * Iban.php
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

namespace App\Rules;

use App\Services\CSV\Converter\Iban as IbanConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * IBAN rule class.
 */
class Iban implements ValidationRule
{
    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute is not a valid IBAN.';
    }

    /**
     * Determine if the given value is a valid IBAN.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = IbanConverter::isValidIban((string)$value);
        if (!$result) {
            $fail($this->message());
        }
    }
}
