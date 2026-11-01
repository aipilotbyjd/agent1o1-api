<?php

namespace App\Services\SuperAdmin\Auth;

use App\Models\SuperAdmin\SuperAdmin;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Where a super admin may connect from: a global allowlist
 * (`super_admin_security.allowed_ips`) applied to everyone before
 * authentication, and an optional per-account list on top of it. An empty
 * list restricts nothing.
 */
class SuperAdminIpPolicy
{
    public function allowsGlobally(?string $ip): bool
    {
        return $this->matches($ip, config('super_admin_security.allowed_ips', []));
    }

    public function allowsAccount(SuperAdmin $admin, ?string $ip): bool
    {
        return $this->matches($ip, $admin->allowed_ips ?? []);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function matches(?string $ip, array $allowed): bool
    {
        if ($allowed === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }
}
