<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ImportFileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,json,xml'],
        ];
    }
}
