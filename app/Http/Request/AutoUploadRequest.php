<?php


namespace App\Http\Request;


class AutoUploadRequest extends Request
{
    /**
     * Verify the request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }


    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'csv'  => 'required|file',
            'json' => 'required|file',
        ];

    }
}
