<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Services\SuperAdmin\Models\SuperAdminModelService;

class StoreRouteRequest extends ModelChangeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'execution_provider' => ['required', 'string', 'max:100', 'in:'.implode(',', app(SuperAdminModelService::class)->knownProviders())],
            'execution_model_id' => ['required', 'string', 'max:200'],
            'priority' => ['sometimes', 'integer', 'between:0,1000'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['execution_provider.in' => 'That is not a provider this platform knows. Add it as an endpoint first, or check config/ai.php.'];
    }
}
