<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Sandbox;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use Illuminate\Validation\Rule;

/**
 * Makes one provider the one tried first, or (with a null key) leaves the
 * order to priority alone.
 */
class SetDefaultSandboxProviderRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'provider' => ['present', 'nullable', 'string', Rule::exists('sandbox_providers', 'key')],
        ];
    }
}
