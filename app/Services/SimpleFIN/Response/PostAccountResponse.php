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
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->account = null;
        if (isset($data['id'])) {
            $this->account = Account::fromArray($data);
        }
        $this->rawData = $data;
    }

    /**
     * @return array
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @return Account|null
     */
    public function getAccount(): ?Account
    {
        return $this->account;
    }
}