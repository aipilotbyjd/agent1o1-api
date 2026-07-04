<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardBasicAuth
{
    /**
     * Protect the ops dashboards (Horizon, Pulse) with HTTP Basic Auth.
     *
     * Open in local. In every other environment the request must carry the
     * configured credentials, so the dashboards are reachable without needing
     * an application login (this is a Passport/API-only app).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = config('services.dashboard.user');
        $password = config('services.dashboard.password');

        if ($user && $password
            && hash_equals((string) $user, (string) $request->getUser())
            && hash_equals((string) $password, (string) $request->getPassword())) {
            return $next($request);
        }

        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Dashboards"',
        ]);
    }
}
