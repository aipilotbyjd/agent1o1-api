<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Models;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;
use App\Services\Ai\AiProviderSettings;
use App\Services\SuperAdmin\Models\SuperAdminProviderSettingsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A change to a provider's switches (`AiProviderSettings`). Always needs a
 * written reason. Turning customer keys off also needs a fresh
 * authenticator code: it changes what every customer with a key for it can
 * use, straight away. `notify` says whether to tell those workspaces when
 * customer keys are switched: `none`, `in_app`, or `email` (in-app and email).
 */
class UpdateProviderSettingsRequest extends StepUpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'totp_code' => [$this->turnsCustomerKeysOff() ? 'required' : 'nullable', 'string'],
            'byok_enabled' => ['sometimes', 'boolean'],
            'connect_enabled' => ['sometimes', 'nullable', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'free_tier_note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'disabled_message' => ['sometimes', 'nullable', 'string', 'max:300'],
            'notify' => ['sometimes', Rule::in(SuperAdminProviderSettingsService::NOTIFY_OPTIONS)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->turnsCustomerKeysOff()) {
                    $this->requireFreshAuthenticatorCode($validator);
                }
            },
        ];
    }

    private function turnsCustomerKeysOff(): bool
    {
        $provider = (string) $this->route('provider');

        return $this->has('byok_enabled')
            && ! $this->boolean('byok_enabled')
            && app(AiProviderSettings::class)->byokEnabled($provider);
    }
}
