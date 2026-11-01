<?php

namespace App\Services\SuperAdmin\Auth;

use App\Enums\SuperAdmin\SuperAdminAuthEvent;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminLoginEvent;
use Illuminate\Support\Str;

/**
 * Writes the sign-in history in `super_admin_login_events`. `$email` is kept
 * for attempts that matched no account, so a spray of guesses stays visible.
 */
class SuperAdminLoginEventRecorder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(SuperAdminAuthEvent $event, ?SuperAdmin $admin, ?string $email = null, array $context = []): SuperAdminLoginEvent
    {
        $request = request();

        return SuperAdminLoginEvent::query()->create([
            'super_admin_id' => $admin?->id,
            'email_attempted' => $admin === null ? $email : null,
            'event' => $event->value,
            'context' => $context === [] ? null : $context,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
