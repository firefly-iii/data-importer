<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;

use Carbon\Carbon;
use App\Services\Akahu\Model\Transaction\Meta;

// The base NZFCC category that the transaction belongs to. Also included
// is a map of less specific category groupings that this NZFCC category
// is part of (by default Akahu will include personal_finance). Custom
// category groupings can be configured for your application if required.
// Only present when you have permission to view enriched transactions.
final class Category
{
    // The NZFCC Category ID
    private ?string $nzfccId = null;

    // The NZFCC Category Name
    private ?string $name = null;

    //
    // Higher level groupings that a category belongs to.
    //

    private ?PersonalFinanceGroup $personalFinanceGroup = null;

    /**
     * Parse a category structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $category = new self();

        $category->nzfccId = $json['_id'] ?? null;
        $category->name = $json['name'] ?? null;

        if (isset($json['groups']) && is_array($json['groups'])) {
            $category->groups = [];

            if (isset($json['groups'][PersonalFinanceGroup::CLASSIFIER])) {
                $groupJson = $json['groups'][PersonalFinanceGroup::CLASSIFIER];
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
