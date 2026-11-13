<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Sandbox;

use App\Enums\Sandbox\SandboxDriverType;
use App\Enums\Sandbox\SandboxMode;
use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Services\Sandbox\SandboxUrlGuard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adds a provider code may run on, with its credentials. A reason and a
 * fresh authenticator code, because it stores a secret and points the
 * server at an address. Providers that live at an address you type in must
 * be a separate machine on a public address (see `SandboxUrlGuard`).
 */
class StoreSandboxProviderRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = SandboxDriverType::tryFrom((string) $this->input('driver'));

        return [
            ...parent::rules(),
            'key' => ['required', 'string', 'between:2,40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('sandbox_providers', 'key')],
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::enum(SandboxDriverType::class)],
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
            ...($type !== null ? $this->driverRules($type) : []),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function driverRules(SandboxDriverType $type): array
    {
        $rules = [];

        foreach ($type->configRules() as $key => $rule) {
            $rules["config.{$key}"] = $rule;
        }

        foreach ($type->credentialRules() as $key => $rule) {
            $rules["credentials.{$key}"] = $key === $type->requiredCredential() ? ['required', 'string', 'max:500'] : $rule;
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkAddresses($validator),
            fn (Validator $validator) => $this->requireFreshAuthenticatorCode($validator),
        ];
    }

    protected function checkAddresses(Validator $validator): void
    {
        $type = SandboxDriverType::tryFrom((string) $this->input('driver', $this->route('provider')?->driver ?? ''));

        if ($type === null) {
            return;
        }

        foreach (['config.url', 'config.api_url'] as $field) {
            if (! $this->filled($field) || $validator->errors()->has($field)) {
                continue;
            }

            $problem = app(SandboxUrlGuard::class)->problem((string) $this->input($field));

            if ($problem !== null) {
                $validator->errors()->add($field, $problem);
            }
        }
    }
}
