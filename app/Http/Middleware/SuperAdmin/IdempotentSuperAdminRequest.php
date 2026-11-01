<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a money-moving request safe to retry. When the client sends an
 * `Idempotency-Key`, the first answer is kept (per super admin) and repeated
 * for the same key instead of running the action again; the same key with a
 * different request body is refused. Without the header nothing changes.
 *
 * The authenticator code is left out of the comparison (a retry a minute
 * later legitimately carries a different one), and so is the order the fields
 * were sent in.
 */
class IdempotentSuperAdminRequest
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');
        $admin = $request->user('super-admin');

        if ($key === null || $key === '' || $admin === null) {
            return $next($request);
        }

        $cacheKey = 'super-admin-idempotency:'.$admin->id.':'.hash('sha256', $key);
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode(Arr::sortRecursive($request->except('totp_code'))));

        $stored = Cache::get($cacheKey);

        if ($stored !== null) {
            // Already answered (and audited) the first time.
            $request->attributes->set(SuperAdminAuditLogger::AUDITED_ATTRIBUTE, true);

            if ($stored['fingerprint'] !== $fingerprint) {
                return ApiResponse::error('This Idempotency-Key was already used with a different request.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response($stored['body'], $stored['status'], [
                'Content-Type' => 'application/json',
                'Idempotent-Replayed' => 'true',
            ]);
        }

        // A duplicate arriving while the first is still running must not run twice.
        if (! Cache::add($cacheKey.':running', true, 30)) {
            $request->attributes->set(SuperAdminAuditLogger::AUDITED_ATTRIBUTE, true);

            return ApiResponse::error('A request with this Idempotency-Key is still being processed.', Response::HTTP_CONFLICT);
        }

        try {
            $response = $next($request);
        } finally {
            Cache::forget($cacheKey.':running');
        }

        // Validation failures and server errors are worth retrying with the same key.
        if ($response->getStatusCode() < 400) {
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], now()->addHours((int) config('super_admin_security.idempotency_ttl_hours')));
        }

        return $response;
    }
}
