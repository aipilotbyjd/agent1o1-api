<?php

namespace App\Models\Ai;

use App\Services\Ai\ProviderPauses;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A provider the platform has stopped using (see the migration). Saving or
 * deleting one drops `ProviderPauses`' cache, so it takes effect at once.
 */
#[Fillable(['provider', 'reason', 'paused_at'])]
class AiProviderPause extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::saved(fn () => app(ProviderPauses::class)->forget());
        static::deleted(fn () => app(ProviderPauses::class)->forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paused_at' => 'datetime'];
    }
}
