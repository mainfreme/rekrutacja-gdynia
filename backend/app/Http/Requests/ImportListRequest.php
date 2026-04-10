<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ImportListRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->input('per_page', 15),
            'current_page' => $this->input('current_page', 1),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['integer', 'min:1', 'max:100'],
            'current_page' => ['integer', 'min:1'],
        ];
    }
}
