<?php


/*
 * PersonalFinanceGroup.php
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

namespace App\Services\Akahu\Model\Transaction;

final class PersonalFinanceGroup
{
    public const CLASSIFIER  = 'personal_finance';

    private ?string $akahuId = null;
    private ?string $name    = null;

    /**
     * Parse a personal finance group structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $personalFinanceGroup          = new self();

        $personalFinanceGroup->akahuId = $json['_id'] ?? null;
        $personalFinanceGroup->name    = $json['name'] ?? null;

        return $personalFinanceGroup;
    }

    public function getAkahuId(): ?string
    {
        return $this->akahuId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
