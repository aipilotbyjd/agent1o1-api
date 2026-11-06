<?php

namespace App\Models\Ai;

use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\AiProviderSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The super admin's switches for one bring-your-own-key provider (see the
 * migration). Read through `AiProviderSettings`, never directly: a provider
 * with no row keeps its `config/byok.php` defaults. Saving or deleting one
 * drops that cache, so a change takes effect at once. The one-click login
 * app's client id and secret are encrypted and never serialized.
 */
#[Fillable(['provider', 'byok_enabled', 'connect_enabled', 'featured', 'sort_order', 'free_tier_note', 'disabled_message', 'connect_client_id', 'connect_client_secret', 'updated_by'])]
#[Hidden(['connect_client_id', 'connect_client_secret'])]
class AiProviderSetting extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'byok_enabled' => true,
        'featured' => false,
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(AiProviderSettings::class)->forget());
        static::deleted(fn () => app(AiProviderSettings::class)->forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byok_enabled' => 'boolean',
            'connect_enabled' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
            'connect_client_id' => 'encrypted',
            'connect_client_secret' => 'encrypted',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'updated_by');
    }
}
