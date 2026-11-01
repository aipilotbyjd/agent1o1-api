<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillSourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'two_way' => ['required', 'boolean'],
            'branch' => ['nullable', 'string', 'max:255', 'regex:/^[^\s~^:?*\[\\\\]+$/'],
            'credential_id' => ['nullable', 'string'],
        ];
    }
}
