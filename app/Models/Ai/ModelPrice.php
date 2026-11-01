<?php

namespace App\Models\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Services\Ai\ModelPrices;
use Database\Factories\Ai\ModelPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What one backend model costs per million tokens, in US dollars. See the
 * migration for why it hangs off (provider, model id) rather than a route.
 * Saving or deleting one drops `ModelPrices`' cache, so a price change is
 * live for the next call.
 */
#[Fillable(['provider', 'model_id', 'input_per_mtok', 'output_per_mtok', 'source', 'checked_at', 'notes'])]
class ModelPrice extends Model
{
    /** @use HasFactory<ModelPriceFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::saved(fn () => app(ModelPrices::class)->forget());
        static::deleted(fn () => app(ModelPrices::class)->forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_per_mtok' => 'float',
            'output_per_mtok' => 'float',
            'source' => ModelPriceSource::class,
            'checked_at' => 'datetime',
        ];
    }
}
