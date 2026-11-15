<?php

namespace App\Models\Operations;

use App\Enums\Operations\TaskCadence;
use App\Enums\Operations\TaskKind;
use App\Models\SuperAdmin\SuperAdmin;
use Database\Factories\Operations\OperationsTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line on the operations checklist: something to set up once, or to do or
 * check every day, week or month. Tasks with a `key` come from the built-in
 * catalogue (`is_system`); they can be switched off and reworded but not
 * deleted.
 */
#[Fillable(['key', 'title', 'description', 'cadence', 'kind', 'check', 'target', 'due_after_hours', 'sort_order', 'enabled', 'is_system', 'assigned_to', 'created_by'])]
class OperationsTask extends Model
{
    /** @use HasFactory<OperationsTaskFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cadence' => TaskCadence::class,
            'kind' => TaskKind::class,
            'due_after_hours' => 'integer',
            'sort_order' => 'integer',
            'enabled' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OperationsTaskEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(OperationsTaskEntry::class);
    }

    /**
     * @return BelongsTo<SuperAdmin, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'assigned_to');
    }

    public function isAuto(): bool
    {
        return $this->kind === TaskKind::Auto;
    }
}
