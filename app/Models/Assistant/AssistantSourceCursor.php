<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * How far a report has read one app. Advanced only when that app was read
 * successfully, so a failed source catches up on the next run.
 */
#[Fillable(['assistant_briefing_config_id', 'source', 'cursor_at'])]
class AssistantSourceCursor extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cursor_at' => 'datetime',
        ];
    }
}
