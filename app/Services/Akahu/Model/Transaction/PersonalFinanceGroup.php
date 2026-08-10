<?php

declare(strict_types=1);

namespace App\Services\Akahu\Model\Transaction;

use Carbon\Carbon;
use App\Services\Akahu\Model\Transaction\Meta;

final class PersonalFinanceGroup
{
    public const CLASSIFIER = 'personal_finance';

    private ?string $akahuId = null;
    private ?string $name = null;

    /**
     * Parse a personal finance group structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $personalFinanceGroup = new self();

        $personalFinanceGroup->akahuId = $json['_id'] ?? null;
        $personalFinanceGroup->name = $json['name'] ?? null;

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
