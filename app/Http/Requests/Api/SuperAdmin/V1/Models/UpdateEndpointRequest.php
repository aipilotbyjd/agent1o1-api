<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Services\Ai\EndpointUrlGuard;
use Illuminate\Validation\Validator;

/**
 * Edits an endpoint. The slug never changes (routes refer to it). Sending
 * `api_key` replaces the key; sending `clear_api_key` removes it; sending
 * neither leaves it alone. The two request limits are cleared by sending null.
 */
class UpdateEndpointRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['sometimes', 'string', 'max:120'],
            'base_url' => ['sometimes', 'string', 'max:500'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'clear_api_key' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
            'workspace_requests_per_minute' => ['sometimes', 'nullable', 'integer', 'between:1,1000000'],
            'total_requests_per_minute' => ['sometimes', 'nullable', 'integer', 'between:1,1000000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('base_url') && ! $validator->errors()->has('base_url')) {
                    $problem = app(EndpointUrlGuard::class)->problem((string) $this->input('base_url'));

                    if ($problem !== null) {
                        $validator->errors()->add('base_url', $problem);
                    }
                }
            },
            fn (Validator $validator) => $this->requireFreshAuthenticatorCode($validator),
        ];
    }
}
