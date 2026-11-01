<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Broadcasts\Announcement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * While a published maintenance notice with `blocks_writes` is inside its
 * window, requests that change data get a 503 with `Retry-After`. Reads pass,
 * and so do the paths in `config('broadcasts.maintenance.allow')` and the Super
 * Admin API (so an operator can always end the window).
 *
 * Fails open: if the lookup itself breaks, the request goes through. A
 * maintenance notice must never be the reason the product is down.
 */
class BlockWritesDuringMaintenance
{
    private const READ_ONLY_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_ONLY_METHODS, true) || $this->isExempt($request)) {
            return $next($request);
        }

        $window = $this->openWindow();

        if ($window === null) {
            return $next($request);
        }

        $response = ApiResponse::error(
            $window->title.': the app is read-only for maintenance'.($window->ends_at ? ' until '.$window->ends_at->toIso8601String() : '').'. Try again shortly.',
            Response::HTTP_SERVICE_UNAVAILABLE,
        );

        if ($window->ends_at !== null) {
            $response->headers->set('Retry-After', (string) max(1, (int) now()->diffInSeconds($window->ends_at, false)));
        }

        return $response;
    }

    private function isExempt(Request $request): bool
    {
        return $request->is('api/'.config('super_admin.prefix').'/*')
            || $request->is(...(array) config('broadcasts.maintenance.allow'));
    }

    private function openWindow(): ?Announcement
    {
        try {
            return Announcement::blockingCached()->first(fn (Announcement $announcement): bool => $announcement->isInWindow());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
