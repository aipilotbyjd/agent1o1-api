<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clamps the `per_page` query parameter to a safe range so a single request
 * cannot force the database and serializer to load an unbounded result set.
 */
class ClampPagination
{
    private const MAX_PER_PAGE = 100;

    private const MIN_PER_PAGE = 1;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('per_page')) {
            $perPage = (int) $request->query('per_page');
            $perPage = max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $perPage ?: self::MIN_PER_PAGE));

            $request->query->set('per_page', (string) $perPage);
            $request->merge(['per_page' => $perPage]);
        }

        return $next($request);
    }
}
