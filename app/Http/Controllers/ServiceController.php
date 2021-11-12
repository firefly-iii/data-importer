<?php

namespace App\Http\Controllers;

use App\Exceptions\ImporterHttpException;
use App\Services\Nordigen\TokenManager;
use App\Services\Spectre\Request\ListCustomersRequest;
use App\Services\Spectre\Response\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

/**
 * Class ServiceController
 */
class ServiceController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function validateSpectre(Request $request): JsonResponse
    {
        $error = $this->verifySpectre();

        if (null !== $error) {
            // send user error:
            return response()->json(['result' => 'NOK', 'message' => $error]);
        }

        return response()->json(['result' => 'OK']);
    }

    /**
     * @return JsonResponse
     */
    public function validateNordigen(): JsonResponse
    {
        $error = $this->verifyNordigen();
        if (null !== $error) {
            // send user error:
            return response()->json(['result' => 'NOK', 'message' => $error]);
        }
        return response()->json(['result' => 'OK']);
    }
}
