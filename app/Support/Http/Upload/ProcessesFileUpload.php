<?php

namespace App\Support\Http\Upload;

use App\Events\ProvidedConfigUpload;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait ProcessesFileUpload
{

    protected function processFileUpload(Request $request, Configuration $configuration): RedirectResponse {

        event(new ProvidedConfigUpload('', $configuration));
        return redirect(route('004-configure.index'));
    }

}
