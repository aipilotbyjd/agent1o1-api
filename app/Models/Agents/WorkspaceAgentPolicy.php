<?php

namespace App\Models\Agents;

use App\Enums\Agents\AutonomyMode;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's guardrails over every agent in the workspace: see the
 * `workspace_agent_policies` migration. `forWorkspace()` returns an unsaved
 * default when none has been set, so callers never branch on "no policy".
 *
 * `guardrails` uses the same rule shape as a tool's `approval_policy`
 * conditions, plus a `tools` list to scope a rule to certain tools and an
 * `effects` list to scope it to certain kinds of action, e.g.
 * `{"effects": ["destructive"], "then": "deny"}`.
 */
#[Fillable(['workspace_id', 'max_autonomy_mode', 'allow_destructive_in_autopilot', 'guardrails', 'approval_ttl_minutes', 'allow_chat_approvals'])]
class WorkspaceAgentPolicy extends Model
{
    use HasUuids;

    public const int DEFAULT_APPROVAL_TTL_MINUTES = 1440;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'allow_destructive_in_autopilot' => false,
        'approval_ttl_minutes' => self::DEFAULT_APPROVAL_TTL_MINUTES,
        'allow_chat_approvals' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_autonomy_mode' => AutonomyMode::class,
            'allow_destructive_in_autopilot' => 'boolean',
            'guardrails' => 'array',
            'approval_ttl_minutes' => 'integer',
            'allow_chat_approvals' => 'boolean',
        ];
    }

    public static function forWorkspace(string $workspaceId): self
    {
        return self::query()->where('workspace_id', $workspaceId)->first()
            ?? new self(['workspace_id' => $workspaceId]);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
