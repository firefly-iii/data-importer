<?php

declare(strict_types=1);

namespace App\Services\Akahu\Request;

use App\Services\Akahu\Response\GetTransactions\GetTransactionsResponse;
use App\Services\Akahu\Response\GetTransactions\GetTransactionsResponseBuilder;

final class GetTransactionsRequest extends Request
{
    private string $akahuId;
    //    private ?Carbon $startDate;
    //    private ?Carbon $endDate;
    private AkahuDateRange $dateRange;

    public function __construct(string $akahuId, AkahuDateRange $dateRange)
    {
        parent::__construct();

        $this->akahuId   = $akahuId;
        $this->dateRange = $dateRange;
    }

    public function get(): GetTransactionsResponse
    {
        $responseBuilder = new GetTransactionsResponseBuilder();
        $cursor          = null;

        // Akahu's transaction endpoint implements pagination
        do {
            // https://developers.akahu.nz/docs/accessing-transactional-data#getting-a-date-range
            if (null !== $this->dateRange->startDate()) {
                $this->setQueryParam('start', $this->dateRange->startDate()->toISOString());
            }

            if (null !== $this->dateRange->endDate()) {
                $this->setQueryParam('end', $this->dateRange->endDate()->toISOString());
            }

            if (null !== $cursor) {
                $this->setQueryParam('cursor', $cursor);
            }

            $responseJson = $this->authenticatedGet(sprintf('accounts/%s/transactions', $this->akahuId));

            $cursor       = $responseBuilder->submitPageAndGetNextCursor($responseJson);
        } while (null !== $cursor);

        return $responseBuilder->build();
    }
}
