<?php

namespace App\Http\Middleware\SuperAdmin;

use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit by default: every state-changing request leaves a trail even if its
 * controller forgot to write one. When the service already recorded a precise
 * entry (with before/after and reason) this adds nothing; otherwise it writes
 * a generic `request.<route name>` row with the outcome.
 *
 * Because the middleware sits ahead of the ability check, a *denied* attempt
 * (403) is recorded too. Requests that merely failed validation or hit a
 * missing record changed nothing and are left out.
 */
class AuditSuperAdminRequest
{
    private const READ_ONLY_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            SuperAdminAuditLogger::REQUEST_ID_ATTRIBUTE,
            $request->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        );

        $response = $next($request);

        $status = $response->getStatusCode();

        if (in_array($request->method(), self::READ_ONLY_METHODS, true)
            || $request->attributes->get(SuperAdminAuditLogger::AUDITED_ATTRIBUTE)
            || ($status >= 400 && $status !== Response::HTTP_FORBIDDEN)) {
            return $response;
        }

        $this->audit->record(
            $request->user('super-admin'),
            'request.'.($request->route()?->getName() ?? $request->path()),
            after: ['method' => $request->method(), 'status' => $status],
        );

        return $response;
    }
}
