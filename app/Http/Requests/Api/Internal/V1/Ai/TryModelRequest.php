<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use Illuminate\Foundation\Http\FormRequest;

class TryModelRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:1000'],
        ];
    }
}
