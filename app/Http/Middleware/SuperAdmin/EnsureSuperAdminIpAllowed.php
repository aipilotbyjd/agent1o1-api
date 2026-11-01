<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Services\SuperAdmin\Auth\SuperAdminIpPolicy;
use App\Services\SuperAdmin\Auth\SuperAdminLoginEventRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Network allowlist, in two stages:
 *
 *  - `super-admin.ip` (global) runs before authentication on every Super Admin
 *    route, the sign-in endpoints included, and checks the global list.
 *  - `super-admin.ip:account` runs after authentication and checks the
 *    signed-in account's own list.
 *
 * A blocked global attempt is not written to the database: anyone on the
 * internet can send those, and each would cost a row.
 */
class EnsureSuperAdminIpAllowed
{
    public function __construct(
        private readonly SuperAdminIpPolicy $policy,
        private readonly SuperAdminLoginEventRecorder $events,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $stage = 'global'): Response
    {
        if ($stage === 'account') {
            $admin = $request->user('super-admin');

            if ($admin !== null && ! $this->policy->allowsAccount($admin, $request->ip())) {
                $this->events->record(SuperAdminAuthEvent::IpBlocked, $admin, context: ['stage' => 'request']);

                abort(403, 'This account cannot connect from this location.');
            }

            return $next($request);
        }

        abort_unless($this->policy->allowsGlobally($request->ip()), 403, 'Access from this location is not allowed.');

        return $next($request);
    }
}
