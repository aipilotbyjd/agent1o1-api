<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HorizonBasicAuth
{
    /**
     * Protect the Horizon dashboard with HTTP Basic Auth.
     *
     * Access is open in local. In every other environment the request must
     * carry the HORIZON_USER / HORIZON_PASSWORD credentials, so the dashboard
     * is reachable without needing an application login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = config('services.horizon.user');
        $password = config('services.horizon.password');

        $suppliedUser = (string) $request->getUser();
        $suppliedPassword = (string) $request->getPassword();

        if ($user && $password
            && hash_equals((string) $user, $suppliedUser)
            && hash_equals((string) $password, $suppliedPassword)) {
            return $next($request);
        }

        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Horizon"',
        ]);
    }
}
