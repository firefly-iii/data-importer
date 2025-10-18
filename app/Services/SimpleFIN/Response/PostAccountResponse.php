<?php
/*
 * PostAccountResponse.php
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

namespace App\Services\SimpleFIN\Response;

use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GrumpyDictator\FFIIIApiSupport\Model\Account;

/**
 * Class PostAccountResponse.
 */
class PostAccountResponse extends Response
{
    private ?Account $account;
    private readonly array $rawData;

    /**
     * Response constructor.
     */
    public function __construct(array $data)
    {
        $this->account = null;
        if (isset($data['id'])) {
            $this->account = Account::fromArray($data);
        }
        $this->rawData = $data;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }
}
