<?php

namespace App\Models\SuperAdmin;

use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Enums\SuperAdmin\SuperAdminRole;
use Database\Factories\SuperAdmin\SuperAdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * One person running the platform. Not a `User`: a separate table, guard and
 * token store (see `docs/SUPER_ADMIN_PLAN.md`). The security-relevant columns
 * (`role`, two-factor state, `disabled_at`, lockout counters) are intentionally
 * outside `Fillable` and only ever set through the Super Admin services.
 */
#[Fillable(['name', 'email', 'password', 'allowed_ips'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class SuperAdmin extends Authenticatable
{
    /** @use HasFactory<SuperAdminFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => SuperAdminRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'allowed_ips' => 'array',
            'password_changed_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(SuperAdminToken::class);
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(SuperAdminLoginEvent::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isOwner(): bool
    {
        return $this->role === SuperAdminRole::Owner;
    }

    public function hasAbility(SuperAdminAbility $ability): bool
    {
        return $this->role->allows($ability);
    }

    /**
     * A password that was never changed since provisioning (a temporary one),
     * or is older than the rotation window, blocks everything but changing it.
     */
    public function passwordExpired(): bool
    {
        if ($this->password_changed_at === null) {
            return true;
        }

        $rotationDays = (int) config('super_admin_auth.password.rotation_days');

        return $rotationDays > 0 && $this->password_changed_at->lt(now()->subDays($rotationDays));
    }
}
