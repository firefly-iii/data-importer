<?php

declare(strict_types=1);

namespace App\Services\Spectre\Request;

use App\Services\Shared\Response\Response;
use App\Services\Spectre\Response\ErrorResponse;
use App\Services\Spectre\Response\PutRefreshConnectionResponse;

/**
 * Class PutRefreshConnectionRequest
 */
class PutRefreshConnectionRequest extends Request
{
    public string $connection;

    /**
     * ListCustomersRequest constructor.
     *
     * @param string $url
     * @param string $appId
     * @param string $secret
     */
    public function __construct(string $url, string $appId, string $secret)
    {
        $this->setBase($url);
        $this->setAppId($appId);
        $this->setSecret($secret);
        $this->setUrl('connections/%s/refresh');
    }

    /**
     * @inheritDoc
     */
    public function get(): Response
    {
    }

    /**
     * @inheritDoc
     */
    public function post(): Response
    {
    }

    /**
     * @inheritDoc
     */
    public function put(): Response
    {
        $this->setUrl(sprintf($this->getUrl(), $this->connection));

        $response = $this->sendUnsignedSpectrePut([]);

        // could be error response:
        if (isset($response['error']) && !isset($response['data'])) {
            return new ErrorResponse($response);
        }

        return new PutRefreshConnectionResponse($response['data']);
    }

    /**
     * @param string $connection
     */
    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

}
