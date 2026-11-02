<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An A/B test: weighted variants (each with its own settings), a guard
 * metric that stops a losing variant, and a status (draft, running,
 * stopped, finished). See `Experiments`.
 */
#[Fillable(['key', 'name', 'hypothesis', 'variants', 'guard_metric', 'guard_threshold', 'status', 'stopped_reason', 'started_at', 'stopped_at'])]
class Experiment extends Model
{
    use HasUuids;

    /**
     * @return HasMany<ExperimentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExperimentAssignment::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'guard_threshold' => 'float',
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }
}
