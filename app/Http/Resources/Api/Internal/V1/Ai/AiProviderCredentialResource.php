<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Workspaces\Permission;
use App\Models\Ai\AiProviderCredential;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes the key, only `key_hint`, its masked form. `can_manage`
 * is whether the asking member may edit, re-check or remove it: a team key
 * needs `ai-credential.manage`, a personal key is only ever visible to the
 * member who added it.
 *
 * @mixin AiProviderCredential
 */
class AiProviderCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'execution_provider' => $this->execution_provider,
            'provider_label' => (string) config("byok.providers.{$this->execution_provider}.label", $this->execution_provider),
            'scope' => $this->scope->value,
            'is_default' => $this->is_default,
            'name' => $this->name,
            'key_hint' => $this->key_hint,
            // `oauth`: a one-click connection whose token renews itself.
            'auth_method' => $this->auth_method->value,
            'token_expires_at' => $this->token_expires_at,
            'validation_status' => $this->validation_status->value,
            'validation_message' => $this->validation_message,
            'last_validated_at' => $this->last_validated_at,
            'last_used_at' => $this->last_used_at,
            // Kept, but not used while a super admin has the provider switched off.
            'provider_disabled' => ! app(AiProviderSettings::class)->byokEnabled($this->execution_provider),
            'provider_disabled_message' => app(AiProviderSettings::class)->byokEnabled($this->execution_provider)
                ? null
                : app(AiProviderSettings::class)->disabledMessage($this->execution_provider),
            // A personal key kept while the workspace has personal keys off.
            'ignored_by_policy' => $this->scope === ConnectorCredentialScope::Personal
                && ! ($this->workspace->aiKeyPolicy->allow_personal_keys ?? true),
            'can_manage' => $request->user()->can(
                $this->scope === ConnectorCredentialScope::Team ? Permission::AiCredentialManage->value : Permission::AiCredentialUsePersonal->value,
            ),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
