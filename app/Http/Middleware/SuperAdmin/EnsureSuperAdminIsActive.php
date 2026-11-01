<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a super admin whose password has expired (or was never changed from
 * the temporary one) from everything except changing it. A disabled account
 * never gets this far: the guard already refuses its tokens.
 */
class EnsureSuperAdminIsActive
{
    /**
     * Routes an expired password may still use.
     */
    private const ALLOWED_ROUTES = [
        'super-admin.auth.change-password',
        'super-admin.auth.logout',
        'super-admin.auth.me',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('super-admin');

        if ($admin !== null
            && $admin->passwordExpired()
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return ApiResponse::error(
                'Your password has expired. Change it to continue.',
                Response::HTTP_FORBIDDEN,
                ['code' => 'password_expired'],
            );
        }

        return $next($request);
    }
}
