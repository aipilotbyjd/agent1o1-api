<?php

namespace App\Models\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use Database\Factories\SuperAdmin\AlertRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tunable alert signal. `type` picks the evaluator; `config` holds its
 * thresholds. Built-in rules come from `AlertRuleCatalog` and can be tuned or
 * disabled, never deleted.
 */
#[Fillable(['key', 'name', 'type', 'config', 'severity', 'cooldown_minutes', 'enabled', 'snoozed_until', 'created_by'])]
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'severity' => AlertSeverity::class,
            'cooldown_minutes' => 'integer',
            'enabled' => 'boolean',
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AlertEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
