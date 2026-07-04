<?php

namespace App\Http\Requests\Api\V1\Credential;

use Illuminate\Foundation\Http\FormRequest;

class InitiateOAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credential_type_key' => ['required', 'string', 'exists:credential_types,key'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
