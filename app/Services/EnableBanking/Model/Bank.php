<?php

/*
 * Bank.php
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

namespace App\Services\EnableBanking\Model;

/**
 * Class Bank
 * Represents an ASPSP (Account Servicing Payment Service Provider) from Enable Banking
 */
class Bank
{
    public string $name;
    public string $country;
    public string $logo;
    public string $bic;
    public int $maxHistoricalDays;
    public array $supportedServices;

    public static function fromArray(array $array): self
    {
        $bank = new self();
        $bank->name = $array['name'] ?? '';
        $bank->country = $array['country'] ?? '';
        $bank->logo = $array['logo'] ?? '';
        $bank->bic = $array['bic'] ?? '';
        $bank->maxHistoricalDays = (int) ($array['max_historical_days'] ?? 90);
        $bank->supportedServices = $array['supported_services'] ?? [];

        return $bank;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'country' => $this->country,
            'logo' => $this->logo,
            'bic' => $this->bic,
            'max_historical_days' => $this->maxHistoricalDays,
            'supported_services' => $this->supportedServices,
        ];
    }
}
