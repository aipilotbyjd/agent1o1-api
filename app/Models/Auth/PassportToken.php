<?php

namespace App\Models\Auth;

use Laravel\Passport\Token;

/**
 * Passport's access token with the device columns this app adds
 * (`ip_address`, `user_agent`, `last_used_at`) cast properly.
 *
 * Registered through `Passport::useTokenModel()` in `AppServiceProvider`: the
 * package's own model declares a fixed `$casts` array, so `last_used_at` would
 * otherwise reach the sessions endpoint as a raw database string while every
 * other timestamp is an ISO-8601 date.
 */
class PassportToken extends Token
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scopes' => 'array',
        'revoked' => 'bool',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}
