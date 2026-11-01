<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Alerts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Alerts\SnoozeAlertRuleRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Alerts\UpdateAlertRuleRequest;
use App\Http\Resources\Api\SuperAdmin\V1\Alerts\AlertRuleResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\AlertEvent;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEngine;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Http\Request;

class AlertRuleController extends Controller
{
    public function __construct(
        private readonly AlertRuleCatalog $catalog,
        private readonly AlertEngine $engine,
        private readonly SuperAdminAuditLogger $audit,
    ) {}

    public function index()
    {
        $this->catalog->sync();

        $rules = AlertRule::query()
            ->withCount(['events as open_events_count' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get()
            ->each(fn (AlertRule $rule) => $this->catalog->withDefaults($rule));

        return ApiResponse::success(AlertRuleResource::collection($rules));
    }

    public function update(UpdateAlertRuleRequest $request, AlertRule $rule)
    {
        $before = $rule->only(['severity', 'cooldown_minutes', 'enabled', 'config']);

        $rule->fill([
            ...$request->safe()->only(['severity', 'cooldown_minutes', 'enabled']),
            ...($request->has('config') ? ['config' => [...($rule->config ?? []), ...$request->validated('config')]] : []),
        ])->save();

        $this->audit->record($request->user('super-admin'), 'alerts.rule_updated', $rule, $before, $rule->only(['severity', 'cooldown_minutes', 'enabled', 'config']), $request->validated('reason'));

        return ApiResponse::success(new AlertRuleResource($this->catalog->withDefaults($rule)), 'Alert rule updated.');
    }

    public function snooze(SnoozeAlertRuleRequest $request, AlertRule $rule)
    {
        $minutes = $request->integer('minutes');

        $rule->forceFill(['snoozed_until' => $minutes === 0 ? null : now()->addMinutes($minutes)])->save();

        $this->audit->record($request->user('super-admin'), 'alerts.rule_snoozed', $rule, null, ['minutes' => $minutes], $request->validated('reason'));

        return ApiResponse::success(new AlertRuleResource($this->catalog->withDefaults($rule)), $minutes === 0 ? 'Snooze cleared.' : 'Alert rule snoozed.');
    }

    /**
     * Sends a test notification so delivery can be checked end to end.
     */
    public function test(Request $request, AlertRule $rule)
    {
        $delivered = $this->engine->sendTest($rule);

        $this->audit->record($request->user('super-admin'), 'alerts.rule_tested', $rule);

        return ApiResponse::success(['dispatched' => $delivered], $delivered
            ? 'A test alert was sent.'
            : 'Nothing was sent: alerting is off or no destination is configured for this severity (see config/admin_alerts.php).');
    }

    /**
     * What the rule would report right now, without raising or recording
     * anything, so a threshold can be tuned without paging anyone.
     */
    public function dryRun(AlertRule $rule)
    {
        $evaluator = $this->catalog->evaluatorFor($rule);

        abort_if($evaluator === null, 422, 'This rule has no evaluator.');

        $this->catalog->withDefaults($rule);

        $alreadyOpen = AlertEvent::query()->active()->where('alert_rule_id', $rule->id)->pluck('fingerprint')->flip();

        $signals = array_map(fn ($signal): array => [
            'fingerprint' => $signal->fingerprint,
            'title' => $signal->title,
            'severity' => ($signal->severity ?? $rule->severity)->value,
            'context' => $signal->context,
            'already_open' => $alreadyOpen->has($signal->fingerprint),
        ], $evaluator->evaluate($rule));

        return ApiResponse::success([
            'rule' => $rule->key,
            'would_fire' => count($signals),
            'would_notify' => count(array_filter($signals, fn (array $signal): bool => ! $signal['already_open'])),
            'signals' => $signals,
        ]);
    }
}
