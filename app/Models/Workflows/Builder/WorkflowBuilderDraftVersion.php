<?php

namespace App\Models\Workflows\Builder;

use App\Models\User;
use Database\Factories\Workflows\Builder\WorkflowBuilderDraftVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_id', 'triggered_by', 'graph_snapshot', 'label'])]
class WorkflowBuilderDraftVersion extends Model
{
    /** @use HasFactory<WorkflowBuilderDraftVersionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graph_snapshot' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
