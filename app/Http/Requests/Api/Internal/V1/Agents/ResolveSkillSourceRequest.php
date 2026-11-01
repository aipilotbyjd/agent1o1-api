<?php

namespace App\Http\Requests\Api\Internal\V1\Agents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveSkillSourceRequest extends FormRequest
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
            'path' => ['present', 'nullable', 'string', 'max:500'],
            'resolution' => ['required', 'in:local,remote,merged'],
            'files' => ['required_if:resolution,merged', 'array', 'max:61'],
            'files.*' => ['string', 'max:262144'],
            'commit_sha' => ['required', 'string', 'max:64'],
        ];
    }
}
