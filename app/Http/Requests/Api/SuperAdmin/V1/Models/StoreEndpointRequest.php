<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Services\Ai\EndpointUrlGuard;
use App\Services\Ai\OperatorEndpoints;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adds an endpoint the platform will send model calls to, with the key it
 * will send. A reason and a fresh authenticator code, because it stores a
 * secret and points the server at an address.
 */
class StoreEndpointRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slug' => [
                'required', 'string', 'between:3,40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ai_endpoints', 'slug'),
                Rule::notIn(app(OperatorEndpoints::class)->reservedSlugs()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
            'workspace_requests_per_minute' => ['nullable', 'integer', 'between:1,1000000'],
            'total_requests_per_minute' => ['nullable', 'integer', 'between:1,1000000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (OperatorEndpoints::slugIsByokShaped((string) $this->input('slug'))) {
                    $validator->errors()->add('slug', 'That name is reserved for workspace keys.');
                }

                if ($validator->errors()->has('base_url')) {
                    return;
                }

                $problem = app(EndpointUrlGuard::class)->problem((string) $this->input('base_url'));

                if ($problem !== null) {
                    $validator->errors()->add('base_url', $problem);
                }
            },
            fn (Validator $validator) => $this->requireFreshAuthenticatorCode($validator),
        ];
    }
}
