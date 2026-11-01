<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\UpdateCreditNotificationsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

/**
 * Gumloop's Credit Notification Preferences: the workspace-level switches
 * over *whether* credit warnings are sent at all. The per-user, per-event
 * choice of how to receive them (in-app, email, webhook) stays on
 * `/notifications/preferences`; these two settings sit above it, which is
 * why turning `out_of_credits_enabled` off silences the event for everyone
 * in the workspace rather than for the caller.
 */
class CreditNotificationController extends Controller
{
    public function show(Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingView);

        return ApiResponse::success(['credit_notifications' => $this->state($workspace)]);
    }

    public function update(UpdateCreditNotificationsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::BillingManage);

        if ($request->has('out_of_credits_enabled')) {
            $workspace->out_of_credits_notification_enabled = $request->boolean('out_of_credits_enabled');
        }

        if ($request->has('thresholds')) {
            $workspace->credit_usage_notification_thresholds = $this->normalizeThresholds(
                $request->input('thresholds'),
            );
        }

        $workspace->save();

        return ApiResponse::success(
            ['credit_notifications' => $this->state($workspace->refresh())],
            'Credit notification preferences updated.',
        );
    }

    /**
     * Stored sorted and de-duplicated so the column reads the same way it is
     * enforced, and `null` (reset to defaults) survives round-tripping. An
     * empty array is kept as one: it means "notify me at no threshold",
     * which is a different answer from "I never chose".
     *
     * @param  array<int, mixed>|null  $thresholds
     * @return list<int>|null
     */
    private function normalizeThresholds(?array $thresholds): ?array
    {
        if ($thresholds === null) {
            return null;
        }

        $thresholds = array_unique(array_map(intval(...), $thresholds));

        sort($thresholds);

        return array_values($thresholds);
    }

    /**
     * `thresholds` is what will actually be enforced (the defaults where the
     * workspace never chose), and `is_default` says which of the two that is:
     * the settings screen needs it to show a "reset" affordance.
     *
     * @return array{out_of_credits_enabled: bool, thresholds: list<int>, is_default: bool, default_thresholds: list<int>, maximum_thresholds: int}
     */
    private function state(Workspace $workspace): array
    {
        return [
            'out_of_credits_enabled' => $workspace->out_of_credits_notification_enabled,
            'thresholds' => $workspace->creditUsageNotificationThresholds(),
            'is_default' => $workspace->credit_usage_notification_thresholds === null,
            'default_thresholds' => array_values(config('billing.credit_notifications.default_thresholds', [])),
            'maximum_thresholds' => (int) config('billing.credit_notifications.maximum_thresholds'),
        ];
    }
}
