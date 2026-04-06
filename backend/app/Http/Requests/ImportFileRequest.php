<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportFileRequest extends FormRequest
{
    public function rules()
    {
        return [
            'file' => 'required|file|mimes:csv,json,xml',
            'type' => 'required|string|in:csv,json,xml',
        ];
    }
}