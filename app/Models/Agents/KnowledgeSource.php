<?php

namespace App\Models\Agents;

use App\Enums\Agents\KnowledgeSourceStatus;
use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the knowledge base keeps in sync: a web page, or content in a
 * connected app (a Drive folder, a Gmail label, a repo, a Slack channel).
 * Shared with the workspace, or private to `owner_id` (their Brain).
 */
#[Fillable(['workspace_id', 'owner_id', 'created_by', 'collection', 'type', 'name', 'connector_credential_id', 'config', 'status', 'last_error', 'sync_cursor', 'last_synced_at', 'documents_count', 'chunks_count'])]
class KnowledgeSource extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'documents_count' => 0,
        'chunks_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => KnowledgeSourceType::class,
            'status' => KnowledgeSourceStatus::class,
            'config' => 'array',
            'last_synced_at' => 'datetime',
            'documents_count' => 'integer',
            'chunks_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConnectorCredential::class, 'connector_credential_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    public function isPrivate(): bool
    {
        return $this->owner_id !== null;
    }
}
