<?php

namespace App\Models\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price a backend model had over a stretch of time. Written by
 * `ModelPrice` whenever its rates or status change; never edited after.
 */
#[Fillable(['model_price_id', 'provider', 'model_id', 'version', 'input_per_mtok', 'output_per_mtok', 'cache_read_per_mtok', 'cache_write_per_mtok', 'long_context_threshold', 'long_input_per_mtok', 'long_output_per_mtok', 'status', 'source', 'changed_by', 'effective_from', 'effective_to'])]
class ModelPriceVersion extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<ModelPrice, $this>
     */
    public function price(): BelongsTo
    {
        return $this->belongsTo(ModelPrice::class, 'model_price_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'input_per_mtok' => 'float',
            'output_per_mtok' => 'float',
            'cache_read_per_mtok' => 'float',
            'cache_write_per_mtok' => 'float',
            'long_context_threshold' => 'integer',
            'long_input_per_mtok' => 'float',
            'long_output_per_mtok' => 'float',
            'status' => ModelPriceStatus::class,
            'source' => ModelPriceSource::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }
}
