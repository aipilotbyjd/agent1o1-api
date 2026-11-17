<?php

namespace App\Http\Controllers\Api\Internal\V1\Notifications;

use App\Enums\Notifications\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Notifications\PreferenceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A person's choices for notifications about themselves rather than a
 * workspace (their assistant, their account), plus product updates, which
 * are the marketing opt-out.
 */
class AccountNotificationPreferenceController extends Controller
{
    public function __construct(private readonly PreferenceWriter $writer) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->payloadFor($request->user()));
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->writer->applyToAccount($request->user(), $request->changes());

        return ApiResponse::success($this->payloadFor($request->user()->refresh()), 'Notification preferences saved.');
    }

    /**
     * @return array{preferences: array<int, array{key: string, in_app: bool, email: bool, email_digest: bool}>}
     */
    private function payloadFor(User $user): array
    {
        $preferences = $user->accountNotificationPreferences()
            ->get()
            ->map(fn ($preference): array => [
                'key' => $preference->key,
                'in_app' => $preference->in_app,
                'email' => $preference->email,
                'email_digest' => $preference->email_digest,
            ])
            ->push([
                'key' => NotificationCategory::ProductUpdates->preferenceKey(),
                'in_app' => true,
                'email' => ! $user->hasOptedOutOfMarketing(),
                'email_digest' => false,
            ])
            ->values()
            ->all();

        return ['preferences' => $preferences];
    }
}
