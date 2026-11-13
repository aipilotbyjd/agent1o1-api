<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Sandbox;

use App\Enums\Sandbox\SandboxMode;
use App\Models\Sandbox\SandboxProvider;
use App\Services\Sandbox\SandboxConcurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sandbox provider. Credentials are never returned: only whether they
 * are set and the last four characters of the main one.
 *
 * @mixin SandboxProvider
 */
class SandboxProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->exists ? $this->id : null,
            'key' => $this->key,
            'name' => $this->name,
            'driver' => $this->driver,
            'driver_label' => $this->type()->label(),
            'from_environment' => ! $this->exists,
            'is_enabled' => $this->is_enabled,
            'is_default' => $this->is_default,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'modes' => collect(SandboxMode::cases())->filter(fn (SandboxMode $mode): bool => $this->allowsMode($mode))->map->value->values()->all(),
            'config' => $this->config ?? [],
            'is_configured' => $this->isConfigured(),
            'has_credentials' => filled($this->credentials),
            'credential_hint' => $this->credentialHint(),
            'price_per_hour_usd' => $this->price_per_hour_usd,
            'max_concurrent' => $this->max_concurrent,
            'in_use' => app(SandboxConcurrency::class)->inUse($this->resource),
            'circuit_open' => $this->isCircuitOpen(),
            'circuit_open_until' => $this->circuit_open_until,
            'consecutive_failures' => $this->consecutive_failures,
            'last_error' => $this->last_error,
            'notes' => $this->notes,
            'last_checked_at' => $this->last_checked_at,
            'last_check_ok' => $this->last_check_ok,
            'last_check_message' => $this->last_check_message,
            'created_at' => $this->created_at,
        ];
    }
}
