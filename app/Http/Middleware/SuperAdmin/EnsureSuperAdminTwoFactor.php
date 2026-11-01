<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Models\SuperAdmin\SuperAdminToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything beyond the setup endpoints needs a *full* token, which is only
 * ever issued after a two-factor code, and an account that still has
 * two-factor authentication confirmed. There is no switch to relax this.
 */
class EnsureSuperAdminTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('super-admin');
        $token = $request->attributes->get('super_admin_token');

        abort_unless(
            $admin !== null
                && $token instanceof SuperAdminToken
                && $token->isFull()
                && $admin->hasTwoFactorEnabled(),
            403,
            'Two-factor authentication is required. Finish setup, then sign in again.',
        );

        return $next($request);
    }
}
