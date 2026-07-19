<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_id',
    'workspace_id',
    'title',
    'content',
    'source_type',
    'source_url',
    'file_path',
    'tokens',
    'is_active',
    'sort_order',
    'metadata',
])]
class AgentKnowledge extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'agent_knowledge';

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
