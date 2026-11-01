<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable fact the assistant keeps about its owner, written by the
 * `remember` tool and included in every turn's instructions.
 */
#[Fillable(['assistant_id', 'key', 'value', 'source_session_id'])]
class AssistantMemory extends Model
{
    use HasUuids;

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
