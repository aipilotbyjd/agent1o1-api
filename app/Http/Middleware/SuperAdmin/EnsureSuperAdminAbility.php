<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Enums\SuperAdmin\SuperAdminAbility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `super-admin.can:users.suspend[,more.abilities]`: the account's role must
 * hold every listed ability. The role is read from the database account on
 * every request, never from the token, so a role change applies at once.
 */
class EnsureSuperAdminAbility
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $admin = $request->user('super-admin');

        abort_if($admin === null || $abilities === [], 403, 'Your role does not allow this action.');

        foreach ($abilities as $name) {
            $ability = SuperAdminAbility::tryFrom($name);

            // An unknown ability name is a typo in a route file: fail closed.
            abort_if($ability === null || ! $admin->hasAbility($ability), 403, 'Your role does not allow this action.');
        }

        return $next($request);
    }
}
