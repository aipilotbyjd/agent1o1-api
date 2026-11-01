<?php

namespace App\Http\Controllers\Api\Internal\V1\Broadcasts;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Broadcasts\MarketingPreferences;
use Illuminate\Http\Request;

/**
 * The signed-in member's choice about marketing email, so it can be undone
 * from the app as well as from an email's unsubscribe link. Service messages
 * are not optional and are not listed here.
 */
class CommunicationPreferenceController extends Controller
{
    public function show(Request $request)
    {
        return ApiResponse::success(['marketing_emails' => ! $request->user()->hasOptedOutOfMarketing()]);
    }

    public function update(Request $request, MarketingPreferences $preferences)
    {
        $validated = $request->validate(['marketing_emails' => ['required', 'boolean']]);

        $user = $request->user();

        $validated['marketing_emails'] ? $preferences->optIn($user) : $preferences->optOut($user);

        return ApiResponse::success(['marketing_emails' => ! $user->hasOptedOutOfMarketing()], 'Email preferences saved.');
    }
}
