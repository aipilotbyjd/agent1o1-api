<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplySkillUpstreamRequest extends FormRequest
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
            'fork_sha' => ['required', 'string', 'max:64'],
            'upstream_sha' => ['required', 'string', 'max:64'],
        ];
    }
}
