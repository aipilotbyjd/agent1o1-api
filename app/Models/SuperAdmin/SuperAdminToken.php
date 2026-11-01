<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Super Admin session: an access/refresh token pair, stored as hashes.
 * See the `create_super_admin_tokens_table` migration for the rotation rules.
 */
#[Fillable([
    'super_admin_id', 'family_id', 'scope', 'access_token_hash', 'refresh_token_hash',
    'access_expires_at', 'refresh_expires_at', 'absolute_expires_at', 'revoked_at',
    'ip_address', 'user_agent', 'last_used_at',
])]
#[Hidden(['access_token_hash', 'refresh_token_hash'])]
class SuperAdminToken extends Model
{
    use HasUuids;

    /** Full access: issued only after the two-factor challenge. */
    public const SCOPE_FULL = 'full';

    /** Opens only the password-change and two-factor setup endpoints. */
    public const SCOPE_SETUP = 'setup';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'absolute_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }

    public function isFull(): bool
    {
        return $this->scope === self::SCOPE_FULL;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function accessExpired(): bool
    {
        return $this->access_expires_at->isPast() || $this->absolute_expires_at->isPast();
    }

    public function refreshExpired(): bool
    {
        return $this->refresh_expires_at === null
            || $this->refresh_expires_at->isPast()
            || $this->absolute_expires_at->isPast();
    }
}
