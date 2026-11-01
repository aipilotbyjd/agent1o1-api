<?php

namespace App\Http\Resources\Api\Internal\V1\Ai;

use App\Enums\Workspaces\Permission;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkspaceAiKeyPolicy
 */
class WorkspaceAiKeyPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform_usage' => $this->platform_usage->value,
            'allow_personal_keys' => $this->allow_personal_keys,
            'can_manage' => $request->user()->can(Permission::AiCredentialManage->value),
            'updated_at' => $this->updated_at,
        ];
    }
}
