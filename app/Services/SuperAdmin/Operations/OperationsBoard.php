<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskCadence;
use App\Enums\Operations\TaskEntryType;
use App\Enums\Operations\TaskKind;
use App\Enums\Operations\TaskStatus;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use App\Models\SuperAdmin\SuperAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out where every checklist task stands: a manual task from what people
 * ticked this period, an automatic one from running its check.
 */
class OperationsBoard
{
    public function __construct(private readonly OperationsChecks $checks) {}

    /**
     * @return Collection<int, TaskState>
     */
    public function states(bool $includeDisabled = false): Collection
    {
        $tasks = OperationsTask::query()
            ->with('assignee:id,name')
            ->when(! $includeDisabled, fn ($query) => $query->where('enabled', true))
            ->get()
            ->sortBy([
                fn (OperationsTask $a, OperationsTask $b): int => $this->order($a->cadence) <=> $this->order($b->cadence),
                ['sort_order', 'asc'],
                ['title', 'asc'],
            ])
            ->values();

        $now = CarbonImmutable::now()->utc();
        $periods = $tasks->mapWithKeys(fn (OperationsTask $task): array => [$task->id => TaskPeriod::current($task->cadence, $now, $task->created_at)]);
        $entries = $this->entriesFor($tasks, $periods);
        $lastDone = $this->lastDone($tasks);

        return $tasks->map(function (OperationsTask $task) use ($periods, $entries, $lastDone, $now): TaskState {
            $period = $periods[$task->id];
            // A task added mid-period gets its full time from when it was added, not from the start of the period.
            $dueAt = $period->dueAt($task->due_after_hours)->max(CarbonImmutable::parse($task->created_at)->utc()->addHours($task->due_after_hours));
            $lastDoneAt = isset($lastDone[$task->id]) ? CarbonImmutable::parse($lastDone[$task->id])->utc() : null;

            if ($task->kind === TaskKind::Auto) {
                $result = $this->checks->run($task->check);

                return new TaskState($task, $result->status, $result->message, null, null, null, null, $lastDoneAt);
            }

            $mine = $entries->get($task->id, collect());
            $done = $mine->first(fn (OperationsTaskEntry $entry): bool => $entry->type === TaskEntryType::Done);
            $snoozed = $mine->first(fn (OperationsTaskEntry $entry): bool => $entry->type === TaskEntryType::Snoozed && $entry->snoozed_until?->isFuture());

            $status = match (true) {
                $done !== null => TaskStatus::Done,
                $snoozed !== null => TaskStatus::Snoozed,
                $now->gte($dueAt) => TaskStatus::Overdue,
                default => TaskStatus::Due,
            };

            return new TaskState($task, $status, null, $period->key, $dueAt, $done, $snoozed, $lastDoneAt);
        });
    }

    /**
     * Counts for the badge and the dashboard. A task assigned to someone else
     * does not count against the viewer.
     *
     * @param  Collection<int, TaskState>|null  $states
     * @return array{total: int, done: int, due: int, overdue: int, snoozed: int, attention: int}
     */
    public function summary(?SuperAdmin $viewer = null, ?Collection $states = null): array
    {
        $states ??= $this->states();

        $mine = $states->filter(fn (TaskState $state): bool => $viewer === null
            || $state->task->assigned_to === null
            || $state->task->assigned_to === $viewer->id);

        $count = fn (TaskStatus $status): int => $mine->where('status', $status)->count();

        return [
            'total' => $mine->count(),
            'done' => $count(TaskStatus::Done),
            'due' => $count(TaskStatus::Due),
            'overdue' => $count(TaskStatus::Overdue),
            'snoozed' => $count(TaskStatus::Snoozed),
            'attention' => $count(TaskStatus::Due) + $count(TaskStatus::Overdue),
        ];
    }

    private function order(TaskCadence $cadence): int
    {
        return array_search($cadence, [TaskCadence::Daily, TaskCadence::Weekly, TaskCadence::Monthly, TaskCadence::Once], true);
    }

    /**
     * The entries of each task for its current period, newest first.
     *
     * @param  Collection<int, OperationsTask>  $tasks
     * @param  Collection<string, TaskPeriod>  $periods
     * @return Collection<string, Collection<int, OperationsTaskEntry>>
     */
    private function entriesFor(Collection $tasks, Collection $periods): Collection
    {
        $manual = $tasks->filter(fn (OperationsTask $task): bool => $task->kind === TaskKind::Manual);

        if ($manual->isEmpty()) {
            return collect();
        }

        return OperationsTaskEntry::query()
            ->with('actor:id,name')
            ->where(function ($query) use ($manual, $periods): void {
                foreach ($manual->groupBy(fn (OperationsTask $task): string => $periods[$task->id]->key) as $period => $group) {
                    $query->orWhere(fn ($inner) => $inner->whereIn('operations_task_id', $group->pluck('id'))->where('period', $period));
                }
            })
            ->latest('created_at')
            ->get()
            ->groupBy('operations_task_id');
    }

    /**
     * @param  Collection<int, OperationsTask>  $tasks
     * @return array<string, string>
     */
    private function lastDone(Collection $tasks): array
    {
        return OperationsTaskEntry::query()
            ->where('type', TaskEntryType::Done)
            ->whereIn('operations_task_id', $tasks->pluck('id'))
            ->selectRaw('operations_task_id, max(created_at) as last_at')
            ->groupBy('operations_task_id')
            ->pluck('last_at', 'operations_task_id')
            ->all();
    }
}
