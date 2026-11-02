<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Intelligence;

use App\Enums\Ai\PriceEnforcement;
use App\Enums\Billing\SpendKillSwitch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\BillingSettings;
use App\Services\Billing\SpendControls;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Validation\Rule;

/**
 * The operator's levers on AI spend: the kill switch, the platform's daily
 * budget and hard stop, price enforcement, and per-workspace throttles.
 * Every change needs a reason and is audited.
 */
class SpendControlController extends Controller
{
    public function __construct(
        private readonly SpendControls $controls,
        private readonly BillingSettings $settings,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function show()
    {
        return ApiResponse::success($this->state());
    }

    public function update(ReasonRequest $request)
    {
        $data = $request->validate([
            'kill_switch' => ['sometimes', Rule::enum(SpendKillSwitch::class)],
            'platform_daily_budget_usd' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10000000'],
            'platform_hard_stop_ratio' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'price_enforcement' => ['sometimes', Rule::enum(PriceEnforcement::class)],
        ]);

        $before = $this->state();

        if (array_key_exists('kill_switch', $data)) {
            $this->controls->setKillSwitch(SpendKillSwitch::from($data['kill_switch']));
        }

        foreach (['platform_daily_budget_usd' => 'guardrails.platform_daily_budget_usd', 'platform_hard_stop_ratio' => 'guardrails.platform_hard_stop_ratio', 'price_enforcement' => 'price_enforcement'] as $field => $key) {
            if (array_key_exists($field, $data)) {
                $this->settings->set($key, $data[$field]);
            }
        }

        $after = $this->state();
        $this->audit->record($request->user('super-admin'), 'intelligence.spend_controls.updated', null, $before, $after, $request->validated('reason'));

        return ApiResponse::success($after, 'Spend controls saved.');
    }

    public function throttle(ReasonRequest $request, Workspace $workspace)
    {
        $data = $request->validate(['hours' => ['required', 'integer', 'min:1', 'max:720']]);

        $this->controls->throttle($workspace->id, now()->addHours($data['hours']), $request->validated('reason'));
        $this->audit->record($request->user('super-admin'), 'intelligence.workspace.throttled', $workspace, null, ['hours' => $data['hours']], $request->validated('reason'));

        return ApiResponse::success($this->controls->currentThrottle($workspace->id), 'Workspace paused.');
    }

    public function unthrottle(ReasonRequest $request, Workspace $workspace)
    {
        $this->controls->unthrottle($workspace->id);
        $this->audit->record($request->user('super-admin'), 'intelligence.workspace.unthrottled', $workspace, null, null, $request->validated('reason'));

        return ApiResponse::success(null, 'Workspace resumed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return [
            'kill_switch' => $this->controls->killSwitch()->value,
            'platform_daily_budget_usd' => $this->controls->platformDailyBudgetUsd(),
            'platform_hard_stop_ratio' => $this->controls->hardStopRatio(),
            'platform_spend_today_usd' => round($this->controls->platformSpendTodayUsd(), 4),
            'price_enforcement' => PriceEnforcement::current()->value,
        ];
    }
}
