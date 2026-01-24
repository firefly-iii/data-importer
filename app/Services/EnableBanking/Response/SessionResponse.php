<?php

/*
 * SessionResponse.php
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

namespace App\Services\EnableBanking\Response;

use App\Services\Shared\Response\Response;

/**
 * Class SessionResponse
 * Response from POST /sessions endpoint
 */
class SessionResponse extends Response
{
    public string $sessionId = '';
    public array $accounts = [];
    public string $aspsp = '';
    public string $psuType = '';       // API: psu_type (personal, business)
    public ?int $validUntil = null;
    public bool $authorized = false;   // API: authorized flag
    public string $status = '';        // API: status

    public function __construct(array $data = [])
    {
        $this->sessionId = $data['session_id'] ?? $data['id'] ?? '';
        $this->accounts = $data['accounts'] ?? [];
        // aspsp can be an object with name or a string
        $this->aspsp = is_array($data['aspsp'] ?? null)
            ? ($data['aspsp']['name'] ?? '')
            : ($data['aspsp'] ?? '');
        $this->psuType = $data['psu_type'] ?? '';
        $this->authorized = (bool) ($data['authorized'] ?? false);
        $this->status = $data['status'] ?? '';

        // valid_until comes as RFC3339 string in access object, convert to timestamp
        $validUntil = $data['access']['valid_until'] ?? null;
        if (is_string($validUntil)) {
            $this->validUntil = strtotime($validUntil) ?: null;
        } else {
            $this->validUntil = $validUntil;
        }
    }

    public static function fromArray(array $array): self
    {
        return new self($array);
    }
}
