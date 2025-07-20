<?php

declare(strict_types=1);

namespace App\Services\CSV\Converter;

use App\Support\Facades\Steam;

class AccountNumber implements ConverterInterface
{
    public function convert(mixed $value): string
    {
        $value = (string)$value;

        // replace spaces from cleaned string.
        return str_replace("\x20", '', Steam::cleanStringAndNewlines($value));
    }

    public function setConfiguration(string $configuration): void {}
}
