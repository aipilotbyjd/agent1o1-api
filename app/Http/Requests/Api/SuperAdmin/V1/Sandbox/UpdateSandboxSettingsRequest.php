<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Sandbox;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * The kill switch and the monthly budget. A null budget means no limit.
 */
class UpdateSandboxSettingsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'kill_switch' => ['sometimes', 'boolean'],
            'monthly_budget_usd' => ['sometimes', 'nullable', 'numeric', 'between:0,1000000'],
        ];
    }
}
