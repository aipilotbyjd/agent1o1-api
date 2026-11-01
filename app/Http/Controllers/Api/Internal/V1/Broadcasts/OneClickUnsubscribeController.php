<?php

namespace App\Http\Controllers\Api\Internal\V1\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Broadcasts\MarketingPreferences;

/**
 * RFC 8058 one-click unsubscribe: the mail client POSTs here from the
 * `List-Unsubscribe` header, with no session and no CSRF token. The signature
 * in the URL (see `MarketingPreferences::oneClickUrl()`) is the authentication.
 */
class OneClickUnsubscribeController extends Controller
{
    public function __invoke(User $user, MarketingPreferences $preferences)
    {
        $preferences->optOut($user);

        return ApiResponse::success(null, 'You are unsubscribed from marketing emails.');
    }
}
