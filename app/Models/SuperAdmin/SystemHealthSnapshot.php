<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The result of every health check at one moment. See `HealthSnapshotter`.
 */
#[Fillable(['status', 'checks', 'created_at'])]
class SystemHealthSnapshot extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checks' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
