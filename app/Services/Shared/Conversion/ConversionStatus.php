<?php

/*
 * ConversionStatus.php
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

namespace App\Services\Shared\Conversion;

/**
 * Class ConversionStatus
 */
class ConversionStatus
{
    public const string CONVERSION_DONE    = 'conv_done';

    public const string CONVERSION_ERRORED = 'conv_errored';

    public const string CONVERSION_RUNNING = 'conv_running';

    public const string CONVERSION_WAITING = 'waiting_to_start';
    public array  $errors;
    public array  $messages;
    public string $status;
    public array  $warnings;
    public array  $rateLimits;

    /**
     * ConversionStatus constructor.
     */
    public function __construct()
    {
        $this->status     = self::CONVERSION_WAITING;
        $this->errors     = [];
        $this->warnings   = [];
        $this->messages   = [];
        $this->rateLimits = [];
    }

    /**
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $config             = new self();
        $config->status     = $array['status'];
        $config->errors     = $array['errors'] ?? [];
        $config->warnings   = $array['warnings'] ?? [];
        $config->messages   = $array['messages'] ?? [];
        $config->rateLimits = $array['rate_limits'] ?? [];

        return $config;
    }

    public function toArray(): array
    {
        return [
            'status'      => $this->status,
            'errors'      => $this->errors,
            'warnings'    => $this->warnings,
            'messages'    => $this->messages,
            'rate_limits' => $this->rateLimits,
        ];
    }
}
