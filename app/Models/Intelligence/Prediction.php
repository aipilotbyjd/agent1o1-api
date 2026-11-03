<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One forecast value, kept with its inputs so it can be explained, and with
 * the actual value once that day has passed so its accuracy can be shown.
 */
#[Fillable(['kind', 'subject_type', 'subject_id', 'made_on', 'target_date', 'value', 'lower', 'upper', 'actual', 'inputs'])]
class Prediction extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'made_on' => 'date',
            'target_date' => 'date',
            'value' => 'float',
            'lower' => 'float',
            'upper' => 'float',
            'actual' => 'float',
            'inputs' => 'array',
        ];
    }
}
