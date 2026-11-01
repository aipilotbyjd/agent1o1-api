<?php

namespace App\Services\SuperAdmin\System;

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Jobs\System\FailStuckAgentTurnsJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Finds work that should already have finished. It uses the same rules as the
 * jobs that clean these up (`FailStuckAgentTurnsJob`, `ExpireStaleWaitsJob`)
 * but only reports: anything listed here is something those safety nets did
 * not catch, which is worth a human look.
 */
class StuckWorkDetector
{
    /**
     * @return array{total: int, groups: array<string, array{label: string, count: int, oldest_at: ?string, sample_ids: list<string>}>}
     */
    public function report(): array
    {
        $groups = [
            'runs' => $this->group('Runs still running', $this->stuckRuns(), 'started_at'),
            'agent_turns' => $this->group('Agent turns still running', $this->stuckAgentTurns(), 'started_at'),
            'expired_waits' => $this->group('Waits past their callback deadline', $this->expiredWaits(), 'callback_expires_at'),
            'trigger_events' => $this->group('Trigger events never processed', $this->stuckTriggerEvents(), 'created_at'),
        ];

        return ['total' => array_sum(array_column($groups, 'count')), 'groups' => $groups];
    }

    /**
     * @return Builder<Run>
     */
    private function stuckRuns(): Builder
    {
        return Run::query()
            ->where('runnable_type', '!=', 'agent_session')
            ->where('status', RunStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes((int) config('super_admin_health.stuck.runs_after_minutes')));
    }

    /**
     * @return Builder<Run>
     */
    private function stuckAgentTurns(): Builder
    {
        return Run::query()
            ->where('runnable_type', 'agent_session')
            ->where('status', RunStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes(FailStuckAgentTurnsJob::STUCK_AFTER_MINUTES * 2));
    }

    /**
     * @return Builder<NodeRun>
     */
    private function expiredWaits(): Builder
    {
        return NodeRun::query()
            ->where('status', NodeRunStatus::AwaitingCallback)
            ->whereNotNull('callback_expires_at')
            ->where('callback_expires_at', '<', now()->subMinutes(5));
    }

    /**
     * @return Builder<TriggerEvent>
     */
    private function stuckTriggerEvents(): Builder
    {
        return TriggerEvent::query()
            ->where('status', 'queued')
            ->where('created_at', '<', now()->subMinutes((int) config('super_admin_health.stuck.trigger_events_after_minutes')));
    }

    /**
     * @param  Builder<*>  $query
     * @return array{label: string, count: int, oldest_at: ?string, sample_ids: list<string>}
     */
    private function group(string $label, Builder $query, string $oldestColumn): array
    {
        $oldest = (clone $query)->min($oldestColumn);

        return [
            'label' => $label,
            'count' => (clone $query)->count(),
            'oldest_at' => $oldest === null ? null : Carbon::parse($oldest)->toIso8601String(),
            'sample_ids' => (clone $query)->orderBy($oldestColumn)->limit(10)->pluck('id')->map(fn ($id): string => (string) $id)->all(),
        ];
    }
}
