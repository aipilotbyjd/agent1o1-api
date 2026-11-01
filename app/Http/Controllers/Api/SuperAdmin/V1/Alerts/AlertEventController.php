<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Alerts;

use App\Enums\Notifications\AlertSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuperAdmin\V1\Alerts\AlertEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\SuperAdmin\AlertEvent;
use App\Services\SuperAdmin\Alerts\AlertRuleCatalog;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertEventController extends Controller
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly AlertRuleCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['open', 'acknowledged', 'resolved', 'active'])],
            'severity' => ['nullable', Rule::enum(AlertSeverity::class)],
            'rule' => ['nullable', 'string', 'max:100'],
        ]);

        $events = AlertEvent::query()
            ->with('rule:id,key,name')
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->active())
            ->when(in_array($request->string('status')->toString(), ['open', 'acknowledged', 'resolved'], true), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')->toString()))
            ->when($request->filled('rule'), fn ($query) => $query->whereHas('rule', fn ($rules) => $rules->where('key', $request->string('rule')->toString())))
            ->orderByRaw("case status when 'resolved' then 1 else 0 end")
            ->orderByDesc('last_fired_at')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::paginated(AlertEventResource::collection($events));
    }

    public function show(AlertEvent $alert)
    {
        return ApiResponse::success(new AlertEventResource($alert->load('rule:id,key,name')));
    }

    /**
     * Open counts, how quickly alerts get acknowledged, and which rules make the most noise.
     */
    public function summary(Request $request)
    {
        $days = max(1, min($request->integer('days', 30), 365));
        $since = now()->subDays($days);

        $recent = AlertEvent::query()->with('rule:id,key,name')->where('first_fired_at', '>=', $since)->get();

        $acknowledgeSeconds = $recent->whereNotNull('acknowledged_at')
            ->map(fn (AlertEvent $event): int => (int) $event->first_fired_at->diffInSeconds($event->acknowledged_at, true));

        $noisiest = $recent->groupBy('alert_rule_id')->map(fn ($events): array => [
            'rule' => $events->first()->rule->key,
            'name' => $events->first()->rule->name,
            'events' => $events->count(),
            'fires' => (int) $events->sum('occurrences'),
            'never_acted_on' => $events->where('status', AlertEvent::STATUS_RESOLVED)->whereNull('acknowledged_at')->count(),
        ])->sortByDesc('fires')->values()->take(10)->all();

        return ApiResponse::success([
            'window_days' => $days,
            'open' => [
                'total' => AlertEvent::query()->active()->count(),
                'unacknowledged' => AlertEvent::query()->where('status', AlertEvent::STATUS_OPEN)->count(),
                'by_severity' => AlertEvent::query()->active()->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity')->map(fn ($total): int => (int) $total)->all(),
            ],
            'mean_seconds_to_acknowledge' => $acknowledgeSeconds->isEmpty() ? null : (int) round($acknowledgeSeconds->avg()),
            'events_in_window' => $recent->count(),
            'noisiest_rules' => $noisiest,
        ]);
    }

    public function acknowledge(Request $request, AlertEvent $alert)
    {
        abort_if($alert->isResolved(), 409, 'This alert is already resolved.');

        $alert->forceFill([
            'status' => AlertEvent::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $request->user('super-admin')->id,
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
        ])->save();

        $this->audit->record($request->user('super-admin'), 'alerts.acknowledged', $alert, null, ['title' => $alert->title]);

        return ApiResponse::success(new AlertEventResource($alert->load('rule:id,key,name')), 'Alert acknowledged.');
    }

    public function resolve(Request $request, AlertEvent $alert)
    {
        abort_if($alert->isResolved(), 409, 'This alert is already resolved.');

        $alert->forceFill(['status' => AlertEvent::STATUS_RESOLVED, 'resolved_at' => now()])->save();

        $this->audit->record($request->user('super-admin'), 'alerts.resolved', $alert, null, ['title' => $alert->title]);

        return ApiResponse::success(new AlertEventResource($alert->load('rule:id,key,name')), 'Alert resolved.');
    }
}
