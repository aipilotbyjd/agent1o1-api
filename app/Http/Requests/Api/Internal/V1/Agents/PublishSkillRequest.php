<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishSkillRequest extends FormRequest
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
            'repo' => ['required', 'string', 'max:200'],
            'branch' => ['required', 'string', 'max:255'],
            'credential_id' => ['required', 'string'],
            'path' => ['required', 'string', 'max:200'],
            'keep_synced' => ['required', 'boolean'],
        ];
    }
}
