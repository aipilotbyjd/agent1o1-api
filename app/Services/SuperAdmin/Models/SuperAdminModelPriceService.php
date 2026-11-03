<?php

namespace App\Services\SuperAdmin\Models;

use App\Enums\Ai\ModelPriceSource;
use App\Enums\Ai\ModelPriceStatus;
use App\Models\Ai\ModelPrice;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Maintaining what each backend model costs. A price drives how many credits
 * a call is billed, so every change is audited with its before and after,
 * and opens a new price version (see `ModelPrice`).
 */
class SuperAdminModelPriceService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * Creates the price, or replaces it. Setting a price confirms it, so
     * `checked_at` becomes now; it is verified unless `status` says
     * otherwise, and a verified price records who verified it.
     *
     * @param  array{provider: string, model_id: string, input_per_mtok: float|int|string, output_per_mtok: float|int|string, cache_read_per_mtok?: float|int|string|null, cache_write_per_mtok?: float|int|string|null, long_context_threshold?: int|string|null, long_input_per_mtok?: float|int|string|null, long_output_per_mtok?: float|int|string|null, source: string, status?: string|null, notes?: string|null}  $data
     */
    public function set(SuperAdmin $actor, array $data, string $reason): ModelPrice
    {
        return DB::transaction(function () use ($actor, $data, $reason): ModelPrice {
            $price = ModelPrice::query()->where('provider', $data['provider'])->where('model_id', $data['model_id'])->first() ?? new ModelPrice([
                'provider' => $data['provider'],
                'model_id' => $data['model_id'],
            ]);

            $before = $price->exists ? $this->snapshot($price) : null;
            $status = ModelPriceStatus::tryFrom((string) ($data['status'] ?? '')) ?? ModelPriceStatus::Verified;

            $price->fill([
                'input_per_mtok' => (float) $data['input_per_mtok'],
                'output_per_mtok' => (float) $data['output_per_mtok'],
                'cache_read_per_mtok' => $this->nullableFloat($data['cache_read_per_mtok'] ?? null),
                'cache_write_per_mtok' => $this->nullableFloat($data['cache_write_per_mtok'] ?? null),
                'long_context_threshold' => isset($data['long_context_threshold']) ? (int) $data['long_context_threshold'] : null,
                'long_input_per_mtok' => $this->nullableFloat($data['long_input_per_mtok'] ?? null),
                'long_output_per_mtok' => $this->nullableFloat($data['long_output_per_mtok'] ?? null),
                'source' => ModelPriceSource::from($data['source']),
                'status' => $status,
                'verified_at' => $status === ModelPriceStatus::Verified ? now() : null,
                'verified_by' => $status === ModelPriceStatus::Verified ? $actor->getKey() : null,
                'notes' => $data['notes'] ?? null,
                'checked_at' => now(),
            ])->save();

            $this->audit->record($actor, 'models.price.set', $price, $before, $this->snapshot($price), $reason);

            return $price;
        });
    }

    /**
     * Marks an existing price verified without changing its rates.
     */
    public function verify(SuperAdmin $actor, ModelPrice $price, string $reason): ModelPrice
    {
        return DB::transaction(function () use ($actor, $price, $reason): ModelPrice {
            $before = $this->snapshot($price);

            $price->fill([
                'status' => ModelPriceStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $actor->getKey(),
                'checked_at' => now(),
            ])->save();

            $this->audit->record($actor, 'models.price.verified', $price, $before, $this->snapshot($price), $reason);

            return $price;
        });
    }

    public function remove(SuperAdmin $actor, ModelPrice $price, string $reason): void
    {
        DB::transaction(function () use ($actor, $price, $reason): void {
            $this->audit->record($actor, 'models.price.removed', $price, $this->snapshot($price), null, $reason);

            $price->delete();
        });
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ModelPrice $price): array
    {
        return [
            'provider' => $price->provider,
            'model_id' => $price->model_id,
            'input_per_mtok' => $price->input_per_mtok,
            'output_per_mtok' => $price->output_per_mtok,
            'cache_read_per_mtok' => $price->cache_read_per_mtok,
            'cache_write_per_mtok' => $price->cache_write_per_mtok,
            'long_context_threshold' => $price->long_context_threshold,
            'long_input_per_mtok' => $price->long_input_per_mtok,
            'long_output_per_mtok' => $price->long_output_per_mtok,
            'source' => $price->source->value,
            'status' => $price->status?->value,
        ];
    }
}
