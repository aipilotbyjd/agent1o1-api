<?php

namespace App\Models\Sandbox;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One code run, kept for usage and cost. It holds neither the code nor its
 * output.
 */
#[Fillable(['workspace_id', 'provider', 'source', 'mode', 'language', 'status', 'seconds', 'cost_usd', 'error'])]
class SandboxRun extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seconds' => 'integer',
            'cost_usd' => 'float',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
