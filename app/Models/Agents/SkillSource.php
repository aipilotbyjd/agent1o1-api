<?php

namespace App\Models\Agents;

use App\Enums\Agents\SkillSourceStatus;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\SkillSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A GitHub repository the workspace's skills are synced from: see
 * `Services\Agents\Skills\SkillSync`. Sources are read-only unless
 * two-way sync is explicitly enabled. Sync state and baselines are
 * engine-managed and cannot be mass assigned.
 */
#[Fillable(['workspace_id', 'created_by', 'connector_credential_id', 'repo', 'branch', 'path', 'is_shared'])]
class SkillSource extends Model
{
    /** @use HasFactory<SkillSourceFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'is_shared' => true,
        'skills_count' => 0,
        'two_way' => false,
        'publish_once' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SkillSourceStatus::class,
            'two_way' => 'boolean',
            'publish_once' => 'boolean',
            'repository_private' => 'boolean',
            'fork_request' => 'array',
            'sync_baseline' => 'array',
            'sync_conflicts' => 'array',
            'is_shared' => 'boolean',
            'last_synced_at' => 'datetime',
            'skills_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConnectorCredential::class, 'connector_credential_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /**
     * The repository folder on GitHub, at the synced branch.
     */
    public function url(?string $path = null): string
    {
        $path = trim($path ?? (string) $this->path, '/');

        return "https://github.com/{$this->repo}".($this->branch !== null || $path !== '' ? '/tree/'.($this->branch ?? 'HEAD').($path !== '' ? "/{$path}" : '') : '');
    }
}
