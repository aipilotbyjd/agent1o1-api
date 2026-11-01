<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of a scheduled command, recorded by `ScheduledCommandRecorder`.
 */
#[Fillable(['command', 'started_at', 'finished_at', 'exit_code', 'duration_ms', 'error'])]
class ScheduledCommandRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
