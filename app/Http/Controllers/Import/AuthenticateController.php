<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Session\Constants;
use Illuminate\Http\Request;

/**
 * Class AuthenticateController
 */
class AuthenticateController extends Controller
{
    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        $flow = $request->cookie(Constants::FLOW_COOKIE);
        if ('csv' === $flow) {
            // redirect straight to upload
            return redirect(route('003-upload.index'));
        }

        exit; // here we are deze functies moeten iets anders teruggeven.
        // dus niet true/false of null/string maar evt een error ofzo?
        // plus wat als de values leeg zijn geeft-ie dan false terug of wat anders?
        $verifySpectre = $this->verifySpectre();
        if ('spectre' === $flow && null === $verifySpectre) {
            return redirect(route('003-upload.index'));
        }

        $verifyNordigen = $this->verifyNordigen();
        if ('spectre' === $flow && null === $verifySpectre) {
            return redirect(route('003-upload.index'));
        }
    }

}
