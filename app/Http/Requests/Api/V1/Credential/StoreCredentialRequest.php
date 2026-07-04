<?php

namespace App\Http\Requests\Api\V1\Credential;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CredentialManage->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'data' => ['required', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
