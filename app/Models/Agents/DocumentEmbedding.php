<?php

namespace App\Models\Agents;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\DocumentEmbeddingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chunk of the knowledge base. Shared with the workspace, or private to
 * `owner_id`: a member's own Brain, which only they (and their assistant)
 * can see.
 */
#[Fillable(['workspace_id', 'owner_id', 'collection', 'knowledge_source_id', 'external_id', 'source', 'chunk_index', 'chunk_text', 'embedding', 'metadata', 'ingest_revision'])]
class DocumentEmbedding extends Model
{
    /** @use HasFactory<DocumentEmbeddingFactory> */
    use HasFactory, HasUuids;

    /**
     * Collections starting with this prefix are written by the app itself
     * (`Agent::artifactKnowledgeCollection()`), not chosen by workspace members.
     */
    public const string ARTIFACT_COLLECTION_PREFIX = 'artifacts:';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'collection' => 'default',
        'chunk_index' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'ingest_revision' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    /**
     * Shared chunks, plus `$user`'s own private ones.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $visible) => $visible->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }

    /**
     * Leaves out the app-written artifact collections. Their chunks are
     * indexed from artifacts whose access is decided per artifact
     * (`Artifact::isAccessibleBy()`), so the workspace-wide knowledge-base
     * views must not surface them, only the owning agent's own search does.
     */
    public function scopeExcludingArtifactCollections(Builder $query): Builder
    {
        return $query->where('collection', 'not like', self::ARTIFACT_COLLECTION_PREFIX.'%');
    }

    /**
     * Only the workspace's shared chunks: what agents search.
     */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('owner_id');
    }
}
