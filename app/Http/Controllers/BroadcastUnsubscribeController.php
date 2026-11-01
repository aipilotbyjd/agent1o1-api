<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Broadcasts\MarketingPreferences;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page an email footer's "Unsubscribe" link opens. The URL signature is
 * the authentication (see `MarketingPreferences::pageUrl()`). Opening it only
 * shows the choice, because mail scanners open links on their own; the
 * unsubscribe happens when the form is submitted.
 */
class BroadcastUnsubscribeController extends Controller
{
    public function __construct(private readonly MarketingPreferences $preferences) {}

    public function show(Request $request, User $user): View
    {
        return view('broadcasts.unsubscribe', [
            'unsubscribed' => $user->hasOptedOutOfMarketing(),
            'formUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user): View
    {
        $this->preferences->optOut($user);

        return view('broadcasts.unsubscribe', ['unsubscribed' => true, 'formUrl' => $request->fullUrl()]);
    }
}
