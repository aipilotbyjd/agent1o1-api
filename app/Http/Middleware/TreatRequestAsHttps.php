<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production every request reached the app over https: Cloudflare redirects plain http and
 * only the proxy in front talks to the container. Through Cloudflare the request still arrived
 * looking like http, so a signed link made as https:// was checked against http:// and refused
 * as expired. Marking the request https makes the address Laravel sees match the one it signed.
 */
class TreatRequestAsHttps
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            $request->server->set('HTTPS', 'on');
            $request->headers->set('X-Forwarded-Proto', 'https');
            $request->headers->set('X-Forwarded-Port', '443');
        }

        return $next($request);
    }
}
