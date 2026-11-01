<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a super admin's sign-in history (see `SuperAdminAuthEvent`).
 */
#[Fillable(['super_admin_id', 'email_attempted', 'event', 'context', 'ip_address', 'user_agent'])]
class SuperAdminLoginEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
