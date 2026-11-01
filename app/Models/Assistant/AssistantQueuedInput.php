<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent while a turn was still running. It becomes the next turn
 * as soon as the current one finishes, in the order it was sent.
 */
#[Fillable(['assistant_session_id', 'content', 'consumed_at'])]
class AssistantQueuedInput extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }
}
