<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Sandbox;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

/**
 * The fallback order: the first key is tried first. Lists every provider
 * once.
 */
class ReorderSandboxProvidersRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'distinct', Rule::exists('sandbox_providers', 'key')],
        ];
    }
}
