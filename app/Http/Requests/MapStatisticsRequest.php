<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MapStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game' => ['required', 'string', 'exists:games,code'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
