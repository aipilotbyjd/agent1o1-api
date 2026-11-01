<?php

namespace App\Services\SuperAdmin\Auth;

use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The `super-admin` guard: a request guard that remembers its result on the
 * request rather than on itself. The stock guard keeps the first resolved user
 * for the lifetime of the guard object, which would carry one request's super
 * admin over into the next whenever the container outlives a request (tests,
 * long-running workers).
 */
class SuperAdminGuard extends RequestGuard
{
    private const RESOLVED = 'super_admin_resolved';

    private const ADMIN = 'super_admin';

    public function user(): ?Authenticatable
    {
        $attributes = $this->request->attributes;

        if ($attributes->has(self::RESOLVED)) {
            return $attributes->get(self::ADMIN);
        }

        $admin = call_user_func($this->callback, $this->request, $this->getProvider());

        $attributes->set(self::RESOLVED, true);
        $attributes->set(self::ADMIN, $admin);

        return $admin;
    }
}
