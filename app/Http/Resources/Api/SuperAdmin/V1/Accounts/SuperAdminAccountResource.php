<?php

namespace App\Http\Resources\Api\SuperAdmin\V1\Accounts;

use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never carries the password hash or any two-factor material.
 *
 * @mixin SuperAdmin
 */
class SuperAdminAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'abilities' => $this->role->abilities(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'allowed_ips' => $this->allowed_ips ?? [],
            'password_expired' => $this->passwordExpired(),
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'locked_until' => $this->locked_until,
            'disabled_at' => $this->disabled_at,
            'created_at' => $this->created_at,
        ];
    }
}
