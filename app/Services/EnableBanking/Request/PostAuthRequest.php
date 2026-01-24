<?php

/*
 * PostAuthRequest.php
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

namespace App\Services\EnableBanking\Request;

use App\Exceptions\ImporterHttpException;
use App\Services\EnableBanking\Response\AuthResponse;
use App\Services\Shared\Response\Response;

/**
 * Class PostAuthRequest
 * Initiates the authorization process with a bank
 */
class PostAuthRequest extends Request
{
    private string $aspsp;
    private string $country = '';
    private string $state = '';
    private string $redirectUrl;
    private string $psuType = 'personal';
    private ?int $validUntil = null;

    public function __construct(string $url)
    {
        $this->setBase($url);
        $this->setUrl('auth');
    }

    public function setAspsp(string $aspsp): void
    {
        $this->aspsp = $aspsp;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }

    public function setPsuType(string $psuType): void
    {
        $this->psuType = $psuType;
    }

    public function setValidUntil(?int $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function get(): Response
    {
        throw new ImporterHttpException('PostAuthRequest does not support GET');
    }

    /**
     * @throws ImporterHttpException
     */
    public function post(): Response
    {
        $validUntilTimestamp = $this->validUntil ?? strtotime('+90 days');
        $data = [
            'access' => [
                'valid_until' => date('c', $validUntilTimestamp), // RFC3339 format
            ],
            'aspsp' => [
                'name' => $this->aspsp,
                'country' => $this->country,
            ],
            'state' => $this->state,
            'redirect_url' => $this->redirectUrl,
            'psu_type' => $this->psuType,
        ];

        $json = $this->authenticatedPost($data);

        return AuthResponse::fromArray($json);
    }
}
