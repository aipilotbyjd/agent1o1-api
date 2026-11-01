<?php

namespace App\Models\Ai;

use Database\Factories\Ai\ModelRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One concrete backend that may serve a `ModelCatalog` entry: a real
 * `laravel/ai` provider name (a `Lab` value or a custom `openai-compatible`
 * config key such as `fireworks`/`together`) plus the model id that
 * provider actually expects. `ModelCatalogResolver` orders a catalog
 * entry's enabled routes by `priority` into the array `laravel/ai`'s
 * native failover retries across on a transient error: see the SDK's
 * "Failover" docs. Never serialized to agent/workflow-facing API responses.
 * Routes run on the platform's own keys; a workspace's own key is put in
 * front per call by `ByokProviderRegistrar`.
 */
#[Fillable(['model_catalog_id', 'execution_provider', 'execution_model_id', 'priority', 'is_enabled', 'options', 'failure_count', 'last_failed_at', 'last_checked_at', 'last_check_ok', 'last_check_latency_ms', 'last_check_message'])]
class ModelRoute extends Model
{
    /** @use HasFactory<ModelRouteFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
        'is_enabled' => true,
        'failure_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'options' => 'array',
            'failure_count' => 'integer',
            'last_failed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_check_ok' => 'boolean',
            'last_check_latency_ms' => 'integer',
        ];
    }

    public function modelCatalog(): BelongsTo
    {
        return $this->belongsTo(ModelCatalog::class);
    }
}
