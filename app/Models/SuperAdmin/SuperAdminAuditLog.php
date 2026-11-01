<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change a super admin made: see `SuperAdminAuditLogger`. Append-only:
 * there is no `updated_at` and nothing edits or deletes a row once written.
 */
#[Fillable([
    'super_admin_id', 'actor_label', 'action', 'subject_type', 'subject_id',
    'before', 'after', 'reason', 'ip_address', 'user_agent', 'request_id',
    'sequence', 'previous_hash', 'hash',
])]
class SuperAdminAuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
