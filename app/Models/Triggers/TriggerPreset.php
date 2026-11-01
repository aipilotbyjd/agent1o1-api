<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerType;
use Database\Factories\Triggers\TriggerPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ready-made trigger setup: a provider's webhook (GitHub push, Stripe
 * event...) with its signature scheme and dedupe rules, or a common schedule.
 *
 * Seeded by TriggerPresetsDataset and listed by the trigger-presets catalog.
 * Used by triggers made through the API; trigger nodes on the canvas choose
 * their authentication on the node instead.
 */
#[Fillable([
    'category', 'key', 'name', 'description', 'type', 'signature_scheme',
    'dedupe_header', 'dedupe_payload_path', 'config', 'fields', 'is_active', 'sort_order',
])]
class TriggerPreset extends Model
{
    /** @use HasFactory<TriggerPresetFactory> */
    use HasFactory, HasUuids;

    /**
     * Defaults for a new preset: active, first in its category.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'config' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Triggers created from this preset.
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class, 'preset_id');
    }
}
