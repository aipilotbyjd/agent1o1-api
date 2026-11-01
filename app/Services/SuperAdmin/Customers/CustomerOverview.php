<?php

namespace App\Services\SuperAdmin\Customers;

use App\Enums\RunStatus;
use App\Enums\SuperAdmin\SuperAdminAbility;
use App\Models\Runs\Run;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\SuperAdmin\Billing\SuperAdminBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Everything an account page's header needs, in one read: the facts its health
 * signals are worked out from, the tags, and the last thing an admin did.
 * One call also means one audit row instead of one per number.
 *
 * Parts the admin may not see (runs, billing, the audit log) are left out and
 * named in `skipped`; the page treats a missing fact as "no signal".
 */
class CustomerOverview
{
    public const DAYS = 14;

    public const REFUND_DAYS = 90;

    public function __construct(
        private readonly CustomerAnnotations $annotations,
        private readonly SuperAdminBillingService $billing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(SuperAdmin $admin, Model $subject): array
    {
        $type = CustomerSubjects::label($subject);
        $skipped = [];
        $data = [
            'type' => $type,
            'tags' => $this->annotations->tags($subject),
            'notes_count' => SuperAdminAccountNote::query()->where('subject_type', $type)->where('subject_id', $subject->getKey())->count(),
        ];

        if ($subject instanceof Workspace) {
            if ($admin->hasAbility(SuperAdminAbility::BillingView)) {
                $data['billing'] = $this->billing->summary($subject);
            } else {
                $skipped[] = 'billing';
            }

            if ($admin->hasAbility(SuperAdminAbility::RunsView)) {
                $data['runs'] = $this->runStats(Run::query()->where('workspace_id', $subject->id));
            } else {
                $skipped[] = 'runs';
            }
        }

        if ($subject instanceof User && $admin->hasAbility(SuperAdminAbility::RunsView)) {
            $data['runs'] = $this->runStats(Run::query()->where('triggered_by', $subject->id));
        }

        if ($admin->hasAbility(SuperAdminAbility::AuditView)) {
            $data['last_admin_action'] = $this->lastAdminAction($type, $subject);

            if ($subject instanceof Workspace) {
                $data['refunds'] = ['count' => $this->refunds($subject), 'days' => self::REFUND_DAYS];
            }
        } else {
            $skipped[] = 'audit';
        }

        return $data + ['skipped' => array_values(array_unique($skipped))];
    }

    /**
     * Run counts over the last two weeks, per day and in total, counted by the database (not sampled).
     *
     * @param  Builder<Run>  $runs
     * @return array<string, mixed>
     */
    private function runStats($runs): array
    {
        $today = Carbon::now()->startOfDay();
        $from = $today->copy()->subDays(self::DAYS - 1);

        $rows = (clone $runs)->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, status, count(*) as total')
            ->groupByRaw('date(created_at), status')
            ->get();

        $perDay = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $perDay[$today->copy()->subDays($i)->toDateString()] = 0;
        }

        $last7 = $failedLast7 = $previous7 = $failedPrevious7 = 0;
        $weekStart = $today->copy()->subDays(6)->toDateString();

        foreach ($rows as $row) {
            $day = (string) $row->day;
            $perDay[$day] = ($perDay[$day] ?? 0) + (int) $row->total;
            $failed = $row->status === RunStatus::Failed || $row->status === RunStatus::Failed->value;

            if ($day >= $weekStart) {
                $last7 += (int) $row->total;
                $failedLast7 += $failed ? (int) $row->total : 0;
            } else {
                $previous7 += (int) $row->total;
                $failedPrevious7 += $failed ? (int) $row->total : 0;
            }
        }

        $recent = (clone $runs)->where('created_at', '>', now()->subDay())
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as failed', [RunStatus::Failed->value])
            ->first();

        return [
            'days' => self::DAYS,
            'per_day' => collect($perDay)->map(fn (int $total, string $date): array => ['date' => $date, 'value' => $total])->values()->all(),
            'last_7_days' => $last7,
            'failed_last_7_days' => $failedLast7,
            'previous_7_days' => $previous7,
            'failed_previous_7_days' => $failedPrevious7,
            'last_24_hours' => (int) ($recent->total ?? 0),
            'failed_last_24_hours' => (int) ($recent->failed ?? 0),
            'last_run_at' => (clone $runs)->max('created_at'),
        ];
    }

    /**
     * The newest thing an admin did to the record. Only looking at it does not count.
     *
     * @return array{at: mixed, by: ?string, action: string, reason: ?string}|null
     */
    private function lastAdminAction(string $type, Model $subject): ?array
    {
        $entry = SuperAdminAuditLog::query()
            ->with('admin:id,name,email')
            ->where('subject_type', $type)
            ->where('subject_id', $subject->getKey())
            ->where('action', 'not like', '%viewed')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $entry === null ? null : [
            'at' => $entry->created_at,
            'by' => $entry->admin?->name ?? $entry->admin?->email ?? $entry->actor_label,
            'action' => $entry->action,
            'reason' => $entry->reason,
        ];
    }

    private function refunds(Workspace $workspace): int
    {
        return SuperAdminAuditLog::query()
            ->where('subject_type', CustomerSubjects::WORKSPACE)
            ->where('subject_id', $workspace->id)
            ->where('action', 'billing.refunded')
            ->where('created_at', '>=', now()->subDays(self::REFUND_DAYS))
            ->count();
    }
}
