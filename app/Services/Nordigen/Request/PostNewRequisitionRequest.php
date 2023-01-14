<?php
/*
 * PostNewRequisitionRequest.php
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

namespace App\Services\Nordigen\Request;

use App\Services\Nordigen\Response\NewRequisitionResponse;
use App\Services\Shared\Response\Response;

/**
 * Class PostNewRequisitionRequest
 */
class PostNewRequisitionRequest extends Request
{
    private string $bank;
    private string $reference;
    private string $agreement;

    public function __construct(string $url, string $token)
    {
        $this->setParameters([]);
        $this->setBase($url);
        $this->setToken($token);
        $this->setUrl('api/v2/requisitions/');
        $this->reference = '';
        $this->agreement = '';
    }

    /**
     * @param string $bank
     */
    public function setBank(string $bank): void
    {
        $this->bank = $bank;
    }

    /**
     * @param string $reference
     */
    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    /**
     * @param string $agreement
     */
    public function setAgreement(string $agreement): void
    {
        $this->agreement = $agreement;
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
        // Implement get() method.
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $array =
            [
                'redirect'       => route('010-build-link.callback'),
                'institution_id' => $this->bank,
                'reference'      => $this->reference,
                'agreement'      => $this->agreement,
            ];

        $result = $this->authenticatedJsonPost($array);
        app('log')->debug('Returned from POST: ', $result);
        return new NewRequisitionResponse($result);
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        // Implement put() method.
    }
}
