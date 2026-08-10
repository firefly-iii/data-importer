<?php

declare(strict_types=1);

namespace App\Services\Akahu\Response;

use App\Services\Akahu\Model\Account\Account;
use App\Services\Shared\Response\Response;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ImporterErrorException;

final class GetAccountsResponse extends Response
{
    private array $accounts;

    public function __construct(array $json)
    {
        if (array_key_exists('success', $json) && $json['success']) {
            Log::debug('Akahu GetAccountsRequest returned successfully');

            if (array_key_exists('items', $json)) {
                $this->accounts = array_map(Account::fromJson(...), $json['items']);
                return;
            }
        }

        $msg = 'Akahu api returned badly structured json, expected response to contain';
        $msg .= ' a "success" attribute and an "items" attribute. See logs for more details.';
        Log::error($msg . ' json: "' . json_encode($json) . '"');

        throw new ImporterErrorException($msg);
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }
}
