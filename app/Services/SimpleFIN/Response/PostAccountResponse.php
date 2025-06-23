<?php

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
    private array $rawData;

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
