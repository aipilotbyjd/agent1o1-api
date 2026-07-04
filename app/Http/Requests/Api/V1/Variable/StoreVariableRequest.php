<?php

namespace App\Http\Requests\Api\V1\Variable;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VariableManage->value);
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'value' => ['nullable', 'string', 'max:65535'],
            'is_secret' => ['boolean'],
        ];
    }
}
