<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Behind a reverse proxy or load balancer every request reaches PHP from the
| proxy's address, and the visitor's real address only arrives in
| `X-Forwarded-For`. Laravel believes those headers only from the addresses
| listed here. Left empty, no proxy is trusted: `$request->ip()` is then the
| proxy for everyone, which collapses every per-IP rate limit into a single
| bucket and breaks the Horizon/Pulse IP allow-list.
|
| `TRUSTED_PROXIES` takes a comma-separated list of IPs or CIDR ranges, the
| word `REMOTE_ADDR` to trust the direct caller, or `*` to trust every caller.
| Use `*` only when the app is reachable through the proxy alone: otherwise a
| client can forge `X-Forwarded-For` and pick its own rate-limit bucket.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES'),

];
