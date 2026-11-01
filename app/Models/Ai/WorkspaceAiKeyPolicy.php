<?php

namespace App\Models\Ai;

use App\Enums\Ai\PlatformKeyUsage;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's rules for the workspace's own AI provider keys: when the
 * platform's keys may still be used (`platform_usage`), and whether members
 * may add keys only they use (`allow_personal_keys`, when off, existing
 * personal keys are kept but never used). Applied by
 * `ByokProviderRegistrar`. `forWorkspace()` returns an unsaved default when
 * none has been set, so callers never branch on "no policy".
 */
#[Fillable(['workspace_id', 'platform_usage', 'allow_personal_keys'])]
class WorkspaceAiKeyPolicy extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'platform_usage' => 'fallback',
        'allow_personal_keys' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_usage' => PlatformKeyUsage::class,
            'allow_personal_keys' => 'boolean',
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
