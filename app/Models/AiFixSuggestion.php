<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'execution_id',
    'workspace_id',
    'node_id',
    'node_type',
    'diagnosis',
    'suggestions',
    'status',
])]
class AiFixSuggestion extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'suggestions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Execution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
