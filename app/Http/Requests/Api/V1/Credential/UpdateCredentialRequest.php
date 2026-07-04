<?php

namespace App\Http\Requests\Api\V1\Credential;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CredentialManage->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:100'],
            'data' => ['sometimes', 'array'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
