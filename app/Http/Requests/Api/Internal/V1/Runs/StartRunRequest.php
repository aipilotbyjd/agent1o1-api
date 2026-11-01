<?php

namespace App\Http\Requests\Api\Internal\V1\Runs;

use Illuminate\Foundation\Http\FormRequest;

class StartRunRequest extends FormRequest
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
            'input' => ['nullable', 'array'],
        ];
    }
}
