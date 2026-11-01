<?php

namespace App\Http\Controllers\Api\Internal\V1\Billing;

use App\Enums\Billing\Feature;
use App\Enums\Workspaces\Permission;
use App\Exceptions\FeatureNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Billing\UpdateCreditOverageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditOverage;

/**
 * Gumloop's Subscription-page overage switch: whether the workspace keeps
 * running once its allowance is spent, and the ceiling on what that may
 * cost it. Read with `billing.view`, changed with `billing.manage`: the
 * same split the rest of the billing surface uses, since flipping this on
 * commits the workspace to a charge.
 */
class CreditOverageController extends Controller
{
    public function show(Workspace $workspace, CreditOverage $overage)
    {
        $this->requirePermission(Permission::BillingView);

        return ApiResponse::success(['overage' => $this->state($workspace, $overage)]);
    }

    /**
     * Enabling on a plan that doesn't sell overage is refused rather than
     * silently persisted: the setting would do nothing and the workspace
     * would only find out by being refused a run. Same
     * `FeatureNotAvailableException` (403) a credit-pack checkout raises for
     * the same reason.
     */
    public function update(UpdateCreditOverageRequest $request, Workspace $workspace, CreditOverage $overage)
    {
        $this->requirePermission(Permission::BillingManage);

        $enabled = $request->boolean('enabled');

        if ($enabled && ! $overage->isAvailableTo($workspace)) {
            throw new FeatureNotAvailableException(Feature::CreditOverage);
        }

        $workspace->credit_overage_enabled = $enabled;

        if ($request->has('limit')) {
            $workspace->credit_overage_limit = $request->input('limit') === null
                ? null
                : (int) $request->input('limit');
        }

        $workspace->save();

        return ApiResponse::success(
            ['overage' => $this->state($workspace->refresh(), $overage)],
            'Credit overage updated.',
        );
    }

    /**
     * `limit` is what the workspace chose (`null` = "whatever the default
     * is"), `effective_limit` what will actually be enforced, and
     * `maximum_limit` the highest cap it may choose: the settings screen
     * needs all three to render a slider that can't submit an invalid value.
     *
     * @return array{available: bool, enabled: bool, limit: int|null, effective_limit: int|null, maximum_limit: int|null, credits_used: int, credits_remaining: int|null, credit_value_usd: float}
     */
    private function state(Workspace $workspace, CreditOverage $overage): array
    {
        $period = $workspace->currentUsagePeriod();

        return [
            'available' => $overage->isAvailableTo($workspace),
            'enabled' => $workspace->credit_overage_enabled,
            'limit' => $workspace->credit_overage_limit,
            'effective_limit' => $overage->effectiveLimitFor($workspace),
            'maximum_limit' => $overage->maximumLimit(),
            'credits_used' => $period->overage_credits_used,
            'credits_remaining' => $overage->remainingFor($workspace, $period),
            'credit_value_usd' => (float) config('billing.credit_value_usd'),
        ];
    }
}
