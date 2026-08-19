<?php


/*
 * Category.php
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

// The base NZFCC category that the transaction belongs to. Also included
// is a map of less specific category groupings that this NZFCC category
// is part of (by default Akahu will include personal_finance). Custom
// category groupings can be configured for your application if required.
// Only present when you have permission to view enriched transactions.
final class Category
{
    // The NZFCC Category ID
    private ?string $nzfccId                            = null;

    // The NZFCC Category Name
    private ?string $name                               = null;

    // Higher level groupings that a category belongs to.

    private array $groups                               = [];

    private ?PersonalFinanceGroup $personalFinanceGroup = null;

    /**
     * Parse a category structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $category          = new self();

        $category->nzfccId = $json['_id'] ?? null;
        $category->name    = $json['name'] ?? null;

        if (array_key_exists('groups', $json) && is_array($json['groups'])) {
            $category->groups = []; // TODO this field is not used.

            if (array_key_exists(PersonalFinanceGroup::CLASSIFIER, $json['groups'])) {
                $groupJson                      = $json['groups'][PersonalFinanceGroup::CLASSIFIER];
                $category->personalFinanceGroup = PersonalFinanceGroup::fromJson($groupJson);
            }
        }

        return $category;
    }

    public function getNzfccId(): ?string
    {
        return $this->nzfccId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPersonalFinanceGroup(): ?PersonalFinanceGroup
    {
        return $this->personalFinanceGroup;
    }
}
