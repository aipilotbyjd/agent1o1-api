<?php

namespace App\Models\Operations;

use App\Enums\Operations\TaskEntryType;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\Operations\OperationsTaskEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone ticked a task off or snoozed it for one period. The `period` is the
 * day, week or month it counts for (see `TaskPeriod`), so a task is done for
 * "2026-11-15" or "2026-W46" and starts fresh in the next one.
 */
#[Fillable(['operations_task_id', 'type', 'period', 'note', 'snoozed_until', 'actor_id'])]
class OperationsTaskEntry extends Model
{
    /** @use HasFactory<OperationsTaskEntryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskEntryType::class,
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationsTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(OperationsTask::class, 'operations_task_id');
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'actor_id');
    }
}
