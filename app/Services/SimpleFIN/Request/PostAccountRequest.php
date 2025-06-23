<?php

declare(strict_types=1);

namespace App\Services\SimpleFIN\Request;

use GrumpyDictator\FFIIIApiSupport\Request\Request;
use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GrumpyDictator\FFIIIApiSupport\Response\ValidationErrorResponse;
use App\Services\SimpleFIN\Response\PostAccountResponse;

/**
 * Class PostAccountRequest
 * POST an account to Firefly III.
 */
class PostAccountRequest extends Request
{
    /**
     * PostAccountRequest constructor.
     *
     * @param string $url
     * @param string $token
     */
    public function __construct(string $url, string $token)
    {
        $this->setBase($url);
        $this->setToken($token);
        $this->setUri('accounts');
    }

    /**
     * @return Response
     */
    public function get(): Response
    {
        // TODO: Implement get() method.
    }

    /**
     * @return Response
     */
    public function post(): Response
    {
        $data = $this->authenticatedPost();

        // found error in response:
        if (array_key_exists('errors', $data) && is_array($data['errors'])) {
            return new ValidationErrorResponse($data['errors']);
        }

        // should be impossible to get here (see previous code) but still check.
        if (!array_key_exists('data', $data)) {
            // return with error array:
            if (array_key_exists('errors', $data) && is_array($data['errors'])) {
                return new ValidationErrorResponse($data['errors']);
            }
            // no data array and no error info, that's weird!
            $info = ['unknown_field' => [sprintf('Unknown error: %s', json_encode($data, 0, 16))]];
            return new ValidationErrorResponse($info);
        }

        return new PostAccountResponse($data['data'] ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function put(): Response
    {
        // TODO: Implement put() method.
    }
}