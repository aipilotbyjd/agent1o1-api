<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['experiment_id', 'workspace_id', 'variant', 'assigned_at'])]
class ExperimentAssignment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }
}
