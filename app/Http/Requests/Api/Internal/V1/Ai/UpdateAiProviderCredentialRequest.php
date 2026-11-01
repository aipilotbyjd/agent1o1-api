<?php

namespace App\Http\Requests\Api\Internal\V1\Ai;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiProviderCredentialRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api_key' => ['sometimes', 'string', 'min:8', 'max:500'],
        ];
    }
}
