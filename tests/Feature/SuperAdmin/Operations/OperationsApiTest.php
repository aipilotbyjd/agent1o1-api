<?php

use App\Enums\Operations\TaskCadence;
use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // A Wednesday, mid-morning UTC, so "daily" is due but not overdue and "weekly" is mid-week.
    $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00:00', 'UTC'));

    // The migration seeds the built-in checklist; these tests build their own.
    OperationsTask::query()->delete();

    $this->admin = superAdmin(SuperAdminRole::Owner);
    $this->headers = superAdminHeaders($this->admin);
});

/**
 * Moves the clock and signs in again, because the test token would otherwise
 * expire on the way.
 */
function travelAndSignIn(CarbonImmutable|DateTimeInterface $when): void
{
    test()->travelTo($when);
    test()->headers = superAdminHeaders(test()->admin);
}

function board(): array
{
    return test()->withHeaders(test()->headers)->getJson(saUrl('operations'))->assertOk()->json('data');
}

function taskStatus(string $id): string
{
    return collect(board()['tasks'])->firstWhere('id', $id)['status'];
}

it('lets every role read the checklist, operators tick tasks, and only owners edit the list', function (SuperAdminRole $role, int $read, int $tick, int $configure) {
    $task = OperationsTask::factory()->create();
    $headers = superAdminHeaders(superAdmin($role));

    $this->withHeaders($headers)->getJson(saUrl('operations'))->assertStatus($read);
    $this->withHeaders($headers)->getJson(saUrl('operations/summary'))->assertStatus($read);
    $this->withHeaders($headers)->getJson(saUrl('operations/history'))->assertStatus($read);
    $this->withHeaders($headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertStatus($tick);
    $this->withHeaders($headers)->getJson(saUrl('operations/tasks'))->assertStatus($configure);
})->with([
    'owner' => [SuperAdminRole::Owner, 200, 200, 200],
    'operator' => [SuperAdminRole::Operator, 200, 200, 403],
    'finance' => [SuperAdminRole::Finance, 200, 403, 403],
    'support' => [SuperAdminRole::Support, 200, 403, 403],
    'viewer' => [SuperAdminRole::Viewer, 200, 403, 403],
]);

it('shows a manual daily task as due in the morning and overdue once its time has passed', function () {
    $task = OperationsTask::factory()->create(['due_after_hours' => 12, 'created_at' => now()->subDay()]);

    expect(taskStatus($task->id))->toBe('due');

    travelAndSignIn(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'));

    expect(taskStatus($task->id))->toBe('overdue');
});

it('marks a task done for its period and starts it fresh in the next one', function () {
    $task = OperationsTask::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"), ['note' => 'All quiet'])
        ->assertOk()
        ->assertJsonPath('data.type', 'done')
        ->assertJsonPath('data.period', '2026-11-18');

    $row = collect(board()['tasks'])->firstWhere('id', $task->id);
    expect($row['status'])->toBe('done')->and($row['done']['note'])->toBe('All quiet')->and($row['done']['by'])->toBe($this->admin->name);

    travelAndSignIn(CarbonImmutable::parse('2026-11-19 09:00:00', 'UTC'));

    expect(taskStatus($task->id))->toBe('due');
});

it('keeps a weekly task done all week and resets it on Monday', function () {
    $task = OperationsTask::factory()->cadence(TaskCadence::Weekly)->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk()->assertJsonPath('data.period', '2026-W47');

    travelAndSignIn(CarbonImmutable::parse('2026-11-22 20:00:00', 'UTC'));
    expect(taskStatus($task->id))->toBe('done');

    travelAndSignIn(CarbonImmutable::parse('2026-11-23 01:00:00', 'UTC'));
    expect(taskStatus($task->id))->toBe('due');
});

it('keeps a monthly task done for the month and a setup task done for good', function () {
    $monthly = OperationsTask::factory()->cadence(TaskCadence::Monthly)->create();
    $setup = OperationsTask::factory()->cadence(TaskCadence::Once)->create();

    foreach ([$monthly, $setup] as $task) {
        $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();
    }

    travelAndSignIn(CarbonImmutable::parse('2026-11-30 23:00:00', 'UTC'));
    expect(taskStatus($monthly->id))->toBe('done')->and(taskStatus($setup->id))->toBe('done');

    travelAndSignIn(CarbonImmutable::parse('2026-12-02 09:00:00', 'UTC'));
    expect(taskStatus($monthly->id))->toBe('due')->and(taskStatus($setup->id))->toBe('done');
});

it('turns a setup task overdue when it has waited too long', function () {
    $task = OperationsTask::factory()->cadence(TaskCadence::Once)->create(['due_after_hours' => 72]);

    expect(taskStatus($task->id))->toBe('due');

    travelAndSignIn(now()->addHours(73));

    expect(taskStatus($task->id))->toBe('overdue');
});

it('ticks a task only once, and records who did it in the audit log', function () {
    $task = OperationsTask::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();

    expect(OperationsTaskEntry::query()->count())->toBe(1);
    expect(SuperAdminAuditLog::query()->where('action', 'operations.task_completed')->count())->toBe(1);
});

it('lets a task be taken back, once', function () {
    $task = OperationsTask::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();
    expect(taskStatus($task->id))->toBe('done');

    $this->withHeaders($this->headers)->deleteJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();
    expect(taskStatus($task->id))->toBe('due');

    $this->withHeaders($this->headers)->deleteJson(saUrl("operations/tasks/{$task->id}/complete"))->assertStatus(409);
    expect(SuperAdminAuditLog::query()->where('action', 'operations.task_reopened')->count())->toBe(1);
});

it('snoozes a task with a reason until the time is up', function () {
    $task = OperationsTask::factory()->create(['due_after_hours' => 1]);
    travelAndSignIn(CarbonImmutable::parse('2026-11-18 14:00:00', 'UTC'));

    expect(taskStatus($task->id))->toBe('overdue');

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/snooze"), ['hours' => 24, 'reason' => 'Waiting on the vendor'])
        ->assertOk()
        ->assertJsonPath('data.type', 'snoozed');

    $row = collect(board()['tasks'])->firstWhere('id', $task->id);
    expect($row['status'])->toBe('snoozed')->and($row['snoozed']['reason'])->toBe('Waiting on the vendor');

    travelAndSignIn(now()->addHours(25));
    expect(taskStatus($task->id))->toBe('overdue');
});

it('asks for a reason and an offered length to snooze, and will not snooze a done task', function () {
    $task = OperationsTask::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/snooze"), ['hours' => 5, 'reason' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hours', 'reason']);

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"))->assertOk();
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/snooze"), ['hours' => 24, 'reason' => 'Not needed today'])->assertStatus(409);
});

it('will not tick off or snooze an automatic or switched-off task', function () {
    $auto = OperationsTask::factory()->auto('backups')->create();
    $off = OperationsTask::factory()->disabled()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$auto->id}/complete"))->assertStatus(422);
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$auto->id}/snooze"), ['hours' => 24, 'reason' => 'Looking into it'])->assertStatus(422);
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$off->id}/complete"))->assertStatus(409);
});

it('leaves switched-off tasks off the board', function () {
    OperationsTask::factory()->create(['title' => 'On']);
    OperationsTask::factory()->disabled()->create(['title' => 'Off']);

    expect(collect(board()['tasks'])->pluck('title')->all())->toBe(['On']);
});

it('counts what needs attention, and leaves out tasks assigned to someone else', function () {
    $mine = OperationsTask::factory()->create(['assigned_to' => $this->admin->id]);
    OperationsTask::factory()->create();
    OperationsTask::factory()->create(['assigned_to' => superAdmin(SuperAdminRole::Operator)->id]);
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$mine->id}/complete"))->assertOk();

    $this->withHeaders($this->headers)->getJson(saUrl('operations/summary'))
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.done', 1)
        ->assertJsonPath('data.due', 1)
        ->assertJsonPath('data.attention', 1);
});

it('orders the board by schedule, then sort order', function () {
    OperationsTask::factory()->cadence(TaskCadence::Monthly)->create(['title' => 'Monthly']);
    OperationsTask::factory()->cadence(TaskCadence::Once)->create(['title' => 'Setup']);
    OperationsTask::factory()->create(['title' => 'Daily B', 'sort_order' => 20]);
    OperationsTask::factory()->create(['title' => 'Daily A', 'sort_order' => 10]);
    OperationsTask::factory()->cadence(TaskCadence::Weekly)->create(['title' => 'Weekly']);

    expect(collect(board()['tasks'])->pluck('title')->all())->toBe(['Daily A', 'Daily B', 'Weekly', 'Monthly', 'Setup']);
});

it('adds, edits and removes a task of your own, with the audit log keeping track', function () {
    $assignee = superAdmin(SuperAdminRole::Operator);

    $id = $this->withHeaders($this->headers)->postJson(saUrl('operations/tasks'), [
        'title' => 'Check the status page',
        'cadence' => 'weekly',
        'target' => 'system',
        'assigned_to' => $assignee->id,
    ])->assertCreated()
        ->assertJsonPath('data.kind', 'manual')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.due_after_hours', TaskCadence::Weekly->defaultDueAfterHours())
        ->assertJsonPath('data.assigned_to.id', $assignee->id)
        ->json('data.id');

    $this->withHeaders($this->headers)->patchJson(saUrl("operations/tasks/{$id}"), ['title' => 'Check the public status page', 'due_after_hours' => 48])
        ->assertOk()
        ->assertJsonPath('data.title', 'Check the public status page')
        ->assertJsonPath('data.due_after_hours', 48);

    $this->withHeaders($this->headers)->deleteJson(saUrl("operations/tasks/{$id}"))->assertOk();

    expect(OperationsTask::query()->count())->toBe(0);
    expect(SuperAdminAuditLog::query()->whereIn('action', ['operations.task_created', 'operations.task_updated', 'operations.task_deleted'])->count())->toBe(3);
});

it('checks what is sent when adding or editing a task', function () {
    $this->withHeaders($this->headers)->postJson(saUrl('operations/tasks'), ['title' => '', 'cadence' => 'hourly', 'target' => 'nowhere', 'due_after_hours' => 0, 'assigned_to' => 'nobody'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'cadence', 'target', 'due_after_hours', 'assigned_to']);
});

it('lets a built-in task be reworded and switched off, but not re-timed or deleted', function () {
    $task = OperationsTask::factory()->system('setup.test')->create(['title' => 'Original']);

    $this->withHeaders($this->headers)->patchJson(saUrl("operations/tasks/{$task->id}"), ['title' => 'Reworded', 'enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.title', 'Reworded')
        ->assertJsonPath('data.enabled', false);

    $this->withHeaders($this->headers)->patchJson(saUrl("operations/tasks/{$task->id}"), ['cadence' => 'weekly'])->assertStatus(422);
    $this->withHeaders($this->headers)->deleteJson(saUrl("operations/tasks/{$task->id}"))->assertStatus(409);
});

it('lists the history of what was done and snoozed, newest first, with filters', function () {
    $task = OperationsTask::factory()->create();
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$task->id}/complete"), ['note' => 'Done early'])->assertOk();
    travelAndSignIn(now()->addMinute());
    $other = OperationsTask::factory()->create();
    $this->withHeaders($this->headers)->postJson(saUrl("operations/tasks/{$other->id}/snooze"), ['hours' => 24, 'reason' => 'Not today'])->assertOk();

    $this->withHeaders($this->headers)->getJson(saUrl('operations/history'))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.type', 'snoozed')
        ->assertJsonPath('data.0.task.id', $other->id);

    $this->withHeaders($this->headers)->getJson(saUrl('operations/history?type=done'))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.note', 'Done early');
    $this->withHeaders($this->headers)->getJson(saUrl("operations/history?task_id={$task->id}"))->assertJsonPath('meta.total', 1);
});

it('keeps the time allowed when an edit leaves it blank', function () {
    $task = OperationsTask::factory()->create(['due_after_hours' => 30]);

    $this->withHeaders($this->headers)->patchJson(saUrl("operations/tasks/{$task->id}"), ['title' => 'Renamed', 'due_after_hours' => null])
        ->assertOk()
        ->assertJsonPath('data.due_after_hours', 30);
});

it('gives a task added late in its period its full time, then holds it to the normal time', function () {
    // Sunday evening: this week's weekly deadline (Friday) is long past.
    travelAndSignIn(CarbonImmutable::parse('2026-11-22 20:00:00', 'UTC'));
    $task = OperationsTask::factory()->cadence(TaskCadence::Weekly)->create();

    expect(taskStatus($task->id))->toBe('due');

    travelAndSignIn(CarbonImmutable::parse('2026-11-25 20:00:00', 'UTC'));
    expect(taskStatus($task->id))->toBe('due');

    // The next Friday: the normal deadline of the week that began on Monday the 23rd has passed.
    travelAndSignIn(CarbonImmutable::parse('2026-11-27 01:00:00', 'UTC'));
    expect(taskStatus($task->id))->toBe('overdue');
});
