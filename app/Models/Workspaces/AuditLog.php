<?php

namespace App\Models\Workspaces;

use App\Enums\Workspaces\AuditAction;
use App\Models\User;
use Database\Factories\Workspaces\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of who did what in a workspace. Written by
 * `AuditLogger`; metadata never holds secret values, only names and the
 * attributes that changed.
 */
#[Fillable(['workspace_id', 'actor_id', 'actor_label', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
