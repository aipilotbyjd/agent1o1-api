<?php

namespace App\Services\SuperAdmin\Dashboards;

use App\Services\Http\LocalDevelopmentRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Who may open the operations dashboards (Horizon and Pulse).
 *
 * Both sit on the `web` guard and know nothing about Super Admin tokens, so
 * access is decided by network instead of by login: the request must come
 * from an address in `super_admin_security.dashboard_ips`. An empty list
 * means nobody, so a deployment that never configured it stays closed.
 * (Locally everything is open, as both packages already do by default:
 * `LocalDevelopmentRequest` is the local environment reached by a
 * development host, not `APP_ENV=local` alone.)
 *
 * Put the dashboards behind a VPN or Cloudflare Access as well; this is the
 * floor, not the whole defence.
 */
class DashboardAccess
{
    public function allows(Request $request): bool
    {
        if (app(LocalDevelopmentRequest::class)->matches($request)) {
            return true;
        }

        if (! config('super_admin.enabled')) {
            return false;
        }

        $allowed = config('super_admin_security.dashboard_ips', []);

        return $allowed !== [] && $request->ip() !== null && IpUtils::checkIp($request->ip(), $allowed);
    }
}
