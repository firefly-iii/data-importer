<?php

namespace App\Support\Http\Upload;

trait CollectsSettings
{

    protected function getSimpleFINSettings(): array
    {
        return [
            'token' => old('simplefin_token') ?? config('simplefin.token'),
        ];
    }

}
