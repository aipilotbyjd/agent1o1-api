<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskCadence;
use App\Enums\Operations\TaskEntryType;
use App\Enums\Operations\TaskKind;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Everything that changes the checklist: ticking a task off, taking it back,
 * snoozing it, and adding, editing or removing tasks. Each one is audited.
 */
class TaskActions
{
    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    public function complete(SuperAdmin $actor, OperationsTask $task, ?string $note): OperationsTaskEntry
    {
        $this->ensureManualAndEnabled($task);

        $period = $this->period($task);

        $existing = OperationsTaskEntry::query()
            ->where('operations_task_id', $task->id)
            ->where('period', $period)
            ->where('type', TaskEntryType::Done)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $task, $note, $period): OperationsTaskEntry {
            $entry = $task->entries()->create([
                'type' => TaskEntryType::Done,
                'period' => $period,
                'note' => $note,
                'actor_id' => $actor->id,
            ]);

            $this->audit->record($actor, 'operations.task_completed', $task, after: ['period' => $period, 'note' => $note]);

            return $entry;
        });
    }

    public function reopen(SuperAdmin $actor, OperationsTask $task): void
    {
        $this->ensureManualAndEnabled($task);

        $period = $this->period($task);

        DB::transaction(function () use ($actor, $task, $period): void {
            $removed = $task->entries()->where('period', $period)->where('type', TaskEntryType::Done)->delete();

            abort_if($removed === 0, 409, 'This task is not marked done.');

            $this->audit->record($actor, 'operations.task_reopened', $task, before: ['period' => $period, 'done' => true], after: ['period' => $period, 'done' => false]);
        });
    }

    public function snooze(SuperAdmin $actor, OperationsTask $task, int $hours, string $reason): OperationsTaskEntry
    {
        $this->ensureManualAndEnabled($task);

        $period = $this->period($task);

        abort_if(
            $task->entries()->where('period', $period)->where('type', TaskEntryType::Done)->exists(),
            409,
            'This task is already done.',
        );

        return DB::transaction(function () use ($actor, $task, $hours, $reason, $period): OperationsTaskEntry {
            $until = now()->addHours($hours);

            $entry = $task->entries()->create([
                'type' => TaskEntryType::Snoozed,
                'period' => $period,
                'note' => $reason,
                'snoozed_until' => $until,
                'actor_id' => $actor->id,
            ]);

            $this->audit->record($actor, 'operations.task_snoozed', $task, after: ['until' => $until->toIso8601String()], reason: $reason);

            return $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(SuperAdmin $actor, array $data): OperationsTask
    {
        $cadence = TaskCadence::from($data['cadence']);

        return DB::transaction(function () use ($actor, $data, $cadence): OperationsTask {
            $task = new OperationsTask([
                ...$data,
                'kind' => TaskKind::Manual,
                'is_system' => false,
                'due_after_hours' => $data['due_after_hours'] ?? $cadence->defaultDueAfterHours(),
                'created_by' => $actor->id,
            ]);
            $task->save();

            $this->audit->record($actor, 'operations.task_created', $task, after: $this->snapshot($task));

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SuperAdmin $actor, OperationsTask $task, array $data): OperationsTask
    {
        if ($task->is_system) {
            abort_if(
                isset($data['cadence']) && $data['cadence'] !== $task->cadence->value,
                422,
                'The schedule of a built-in task cannot be changed. Switch it off and add your own instead.',
            );
        }

        // Blank means "leave as it is": the column cannot be empty.
        if (array_key_exists('due_after_hours', $data) && $data['due_after_hours'] === null) {
            unset($data['due_after_hours']);
        }

        return DB::transaction(function () use ($actor, $task, $data): OperationsTask {
            $before = $this->snapshot($task);

            $task->fill($data)->save();

            $this->audit->recordChanges($actor, 'operations.task_updated', $task, $before);

            return $task;
        });
    }

    public function delete(SuperAdmin $actor, OperationsTask $task): void
    {
        abort_if($task->is_system, 409, 'Built-in tasks can be switched off, not deleted.');

        DB::transaction(function () use ($actor, $task): void {
            $this->audit->record($actor, 'operations.task_deleted', $task, before: $this->snapshot($task));

            $task->delete();
        });
    }

    private function ensureManualAndEnabled(OperationsTask $task): void
    {
        abort_if($task->kind === TaskKind::Auto, 422, 'This task checks itself, so there is nothing to tick off.');
        abort_unless($task->enabled, 409, 'This task is switched off.');
    }

    private function period(OperationsTask $task): string
    {
        return TaskPeriod::current($task->cadence, now(), $task->created_at)->key;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(OperationsTask $task): array
    {
        return $task->only(['title', 'description', 'cadence', 'target', 'due_after_hours', 'sort_order', 'enabled', 'assigned_to']);
    }
}
