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
    public string $name = '';
    public string $country = '';
    public string $logo = '';
    public string $bic = '';
    public int $maximumConsentValidity = 7776000;  // API: maximum_consent_validity in seconds
    public bool $beta = false;                      // API: beta flag
    public array $psuTypes = [];                    // API: psu_types (personal, business)
    public array $authMethods = [];                 // API: auth_methods
    public array $requiredPsuHeaders = [];          // API: required_psu_headers

    public static function fromArray(array $array): self
    {
        $bank = new self();
        $bank->name = $array['name'] ?? '';
        $bank->country = $array['country'] ?? '';
        $bank->logo = $array['logo'] ?? '';
        $bank->bic = $array['bic'] ?? '';
        $bank->maximumConsentValidity = (int) ($array['maximum_consent_validity'] ?? 7776000); // default 90 days in seconds
        $bank->beta = (bool) ($array['beta'] ?? false);
        $bank->psuTypes = $array['psu_types'] ?? [];
        $bank->authMethods = $array['auth_methods'] ?? [];
        $bank->requiredPsuHeaders = $array['required_psu_headers'] ?? [];

        return $bank;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'country' => $this->country,
            'logo' => $this->logo,
            'bic' => $this->bic,
            'maximum_consent_validity' => $this->maximumConsentValidity,
            'beta' => $this->beta,
            'psu_types' => $this->psuTypes,
            'auth_methods' => $this->authMethods,
            'required_psu_headers' => $this->requiredPsuHeaders,
        ];
    }
}
