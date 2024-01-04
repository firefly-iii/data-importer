<?php

/*
 * SubmissionStatus.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

namespace App\Services\Shared\Import\Status;

class SubmissionStatus
{
    public const string SUBMISSION_DONE    = 'submission_done';
    public const string SUBMISSION_ERRORED = 'submission_errored';
    public const string SUBMISSION_RUNNING = 'submission_running';
    public const string SUBMISSION_WAITING = 'waiting_to_start';
    public array  $errors;
    public array  $messages;
    public string $status;
    public array  $warnings;

    /**
     * ImportJobStatus constructor.
     */
    public function __construct()
    {
        $this->status   = self::SUBMISSION_WAITING;
        $this->errors   = [];
        $this->warnings = [];
        $this->messages = [];
    }

    /**
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $config           = new self();
        $config->status   = $array['status'];
        $config->errors   = $array['errors'] ?? [];
        $config->warnings = $array['warnings'] ?? [];
        $config->messages = $array['messages'] ?? [];

        return $config;
    }

    public function toArray(): array
    {
        return [
            'status'   => $this->status,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
            'messages' => $this->messages,
        ];
    }
}
