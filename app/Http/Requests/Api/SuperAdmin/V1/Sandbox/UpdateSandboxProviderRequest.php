<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Sandbox;

use App\Enums\Sandbox\SandboxMode;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\SandboxUrlGuard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edits a provider. Its key and kind never change. Credentials sent here
 * replace the stored ones for the keys sent (blank keeps what is there);
 * `clear_credentials` removes them all.
 */
class UpdateSandboxProviderRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provider = $this->route('provider');
        $rules = [
            ...parent::rules(),
            'name' => ['sometimes', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:0,65000'],
            'weight' => ['sometimes', 'integer', 'between:1,100'],
            'modes' => ['sometimes', 'nullable', 'array', 'min:1'],
            'modes.*' => ['string', Rule::enum(SandboxMode::class)],
            'price_per_hour_usd' => ['sometimes', 'numeric', 'between:0,10000'],
            'max_concurrent' => ['sometimes', 'nullable', 'integer', 'between:1,100000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'config' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'array'],
            'clear_credentials' => ['sometimes', 'boolean'],
        ];

        if ($provider instanceof SandboxProvider) {
            foreach ($provider->type()->configRules() as $key => $rule) {
                $rules["config.{$key}"] = array_map(fn (mixed $item): mixed => $item === 'required' ? 'sometimes' : $item, $rule);
            }

            foreach ($provider->type()->credentialRules() as $key => $rule) {
                $rules["credentials.{$key}"] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['config.url', 'config.api_url'] as $field) {
                    if (! $this->filled($field) || $validator->errors()->has($field)) {
                        continue;
                    }

                    $problem = app(SandboxUrlGuard::class)->problem((string) $this->input($field));

                    if ($problem !== null) {
                        $validator->errors()->add($field, $problem);
                    }
                }
            },
            fn (Validator $validator) => $this->requireFreshAuthenticatorCode($validator),
        ];
    }
}
