<?php

namespace App\Http\Requests\Api\Internal\V1\Auth;

use App\Enums\Auth\ApiKeyAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::enum(ApiKeyAbility::class)],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }
}
