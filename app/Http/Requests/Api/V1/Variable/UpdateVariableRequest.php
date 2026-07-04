<?php

namespace App\Http\Requests\Api\V1\Variable;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VariableManage->value);
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string', 'max:65535'],
            'is_secret' => ['boolean'],
        ];
    }
}
