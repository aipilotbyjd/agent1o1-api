<?php

namespace App\Services\SuperAdmin\Models;

use App\Enums\Ai\ModelPriceSource;
use App\Models\Ai\ModelPrice;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Maintaining what each backend model costs. A price drives how many credits
 * a call is billed, so every change is audited with its before and after.
 */
class SuperAdminModelPriceService
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * Creates the price, or replaces it. Setting a price confirms it, so
     * `checked_at` becomes now.
     */
    public function set(SuperAdmin $actor, string $provider, string $modelId, float $input, float $output, ModelPriceSource $source, ?string $notes, string $reason): ModelPrice
    {
        return DB::transaction(function () use ($actor, $provider, $modelId, $input, $output, $source, $notes, $reason): ModelPrice {
            $price = ModelPrice::query()->where('provider', $provider)->where('model_id', $modelId)->first() ?? new ModelPrice([
                'provider' => $provider,
                'model_id' => $modelId,
            ]);

            $before = $price->exists ? $this->snapshot($price) : null;

            $price->fill([
                'input_per_mtok' => $input,
                'output_per_mtok' => $output,
                'source' => $source,
                'notes' => $notes,
                'checked_at' => now(),
            ])->save();

            $this->audit->record($actor, 'models.price.set', $price, $before, $this->snapshot($price), $reason);

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
            'source' => $price->source->value,
        ];
    }
}
