<?php

namespace App\Models\Ai;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Services\Ai\ModelPrices;
use Database\Factories\Ai\ModelPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What one backend model costs per million tokens, in US dollars. See the
 * migration for why it hangs off (provider, model id) rather than a route.
 * Saving or deleting one drops `ModelPrices`' cache, so a price change is
 * live for the next call, and a change to its rates or status opens a new
 * `ModelPriceVersion`, so past charges can always be re-priced exactly.
 */
#[Fillable(['provider', 'model_id', 'input_per_mtok', 'output_per_mtok', 'cache_read_per_mtok', 'cache_write_per_mtok', 'long_context_threshold', 'long_input_per_mtok', 'long_output_per_mtok', 'source', 'status', 'verified_at', 'verified_by', 'checked_at', 'notes'])]
class ModelPrice extends Model
{
    /** @use HasFactory<ModelPriceFactory> */
    use HasFactory, HasUuids;

    /**
     * The columns whose change makes a new price version.
     */
    public const array VERSIONED_COLUMNS = [
        'input_per_mtok', 'output_per_mtok', 'cache_read_per_mtok', 'cache_write_per_mtok',
        'long_context_threshold', 'long_input_per_mtok', 'long_output_per_mtok', 'status',
    ];

    protected static function booted(): void
    {
        static::saved(function (ModelPrice $price): void {
            if ($price->wasRecentlyCreated || $price->wasChanged(self::VERSIONED_COLUMNS)) {
                $price->openVersion();
            }

            app(ModelPrices::class)->forget();
        });

        static::deleted(function (ModelPrice $price): void {
            $price->versions()->whereNull('effective_to')->update(['effective_to' => now()]);

            app(ModelPrices::class)->forget();
        });
    }

    /**
     * @return HasMany<ModelPriceVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModelPriceVersion::class);
    }

    /**
     * The version in effect now, if any.
     */
    public function currentVersion(): ?ModelPriceVersion
    {
        return $this->versions()->whereNull('effective_to')->latest('effective_from')->first();
    }

    public function isVerified(): bool
    {
        return $this->status === ModelPriceStatus::Verified;
    }

    /**
     * Closes the open version and starts the next one from the current
     * attributes.
     */
    private function openVersion(): void
    {
        $now = now();
        $this->versions()->whereNull('effective_to')->update(['effective_to' => $now]);

        $this->versions()->create([
            'provider' => $this->provider,
            'model_id' => $this->model_id,
            'version' => ((int) $this->versions()->max('version')) + 1,
            'input_per_mtok' => $this->input_per_mtok,
            'output_per_mtok' => $this->output_per_mtok,
            'cache_read_per_mtok' => $this->cache_read_per_mtok,
            'cache_write_per_mtok' => $this->cache_write_per_mtok,
            'long_context_threshold' => $this->long_context_threshold,
            'long_input_per_mtok' => $this->long_input_per_mtok,
            'long_output_per_mtok' => $this->long_output_per_mtok,
            'status' => $this->status ?? ModelPriceStatus::Unverified,
            'source' => $this->source ?? ModelPriceSource::Estimate,
            'changed_by' => $this->verified_by,
            'effective_from' => $now,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_per_mtok' => 'float',
            'output_per_mtok' => 'float',
            'cache_read_per_mtok' => 'float',
            'cache_write_per_mtok' => 'float',
            'long_context_threshold' => 'integer',
            'long_input_per_mtok' => 'float',
            'long_output_per_mtok' => 'float',
            'source' => ModelPriceSource::class,
            'status' => ModelPriceStatus::class,
            'verified_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }
}
