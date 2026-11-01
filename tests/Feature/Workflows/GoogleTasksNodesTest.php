<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksCreateTaskListNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksCreateTaskNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksDeleteTaskNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListCompletedTasksNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListTaskListsNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksListTasksNode;
use App\Nodes\Integrations\GoogleTasks\GoogleTasksUpdateTaskNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

const TASKS_BASE = ['access_token' => 'ya29-test'];

it('registers every google tasks node under the google_tasks category', function () {
    foreach (['list_task_lists', 'create_task_list', 'list_tasks', 'create_task', 'update_task', 'delete_task'] as $suffix) {
        $node = app(NodeRegistry::class)->resolve("google_tasks_{$suffix}");

        expect($node->category())->toBe('google_tasks');
    }
});

it('lists task lists in a flat shape', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => [['id' => 'l1', 'title' => 'Work', 'updated' => '2026-01-01T00:00:00Z']]])]);

    $output = (new GoogleTasksListTaskListsNode)->execute(Run::factory()->create(), TASKS_BASE, []);

    expect($output)->toBe(['task_lists' => [['id' => 'l1', 'title' => 'Work', 'updated' => '2026-01-01T00:00:00Z']]]);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ya29-test') && str_contains($request->url(), '/tasks/v1/users/@me/lists'));
});

it('creates a task list', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['id' => 'l2', 'title' => 'Groceries'])]);

    (new GoogleTasksCreateTaskListNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'title' => 'Groceries'], []);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['title'] === 'Groceries');
});

it('lists open tasks of the default list and hides completed ones', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => [['id' => 't1', 'title' => 'Pay rent']]])]);

    (new GoogleTasksListTasksNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'due_before' => '2026-02-01T10:00:00Z'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/lists/%40default/tasks')
        && str_contains($request->url(), 'showCompleted=false')
        && str_contains($request->url(), 'dueMax=2026-02-01T00%3A00%3A00.000Z')
        && ! str_contains($request->url(), 'showHidden'));
});

it('includes completed and hidden tasks when asked', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => []])]);

    (new GoogleTasksListTasksNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_list_id' => 'l1', 'show_completed' => true], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/lists/l1/tasks') && str_contains($request->url(), 'showCompleted=true') && str_contains($request->url(), 'showHidden=true'));
});

it('works as a new task trigger keyed by task id', function () {
    $node = new GoogleTasksListTasksNode;

    expect($node->triggerItems(['items' => [['id' => 't1'], ['id' => 't2'], 'junk']], []))->toHaveCount(2)
        ->and($node->triggerItemId(['id' => 't1']))->toBe('t1')
        ->and($node->triggerName())->toBe('Google Tasks: New task')
        ->and($node->triggerReadConfig([])['max_results'])->toBe(100);
});

it('creates a task with notes, a due date and a parent', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['id' => 't9'])]);

    (new GoogleTasksCreateTaskNode)->execute(Run::factory()->create(), [
        ...TASKS_BASE, 'task_list_id' => 'l1', 'title' => 'Draft', 'notes' => 'ask Ada', 'due' => '2026-03-05T18:30:00+05:30', 'parent_id' => 'p1',
    ], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/lists/l1/tasks?parent=p1')
        && $request->data() === ['title' => 'Draft', 'notes' => 'ask Ada', 'due' => '2026-03-05T00:00:00.000Z']);
});

it('creates a bare task without empty fields', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['id' => 't9'])]);

    (new GoogleTasksCreateTaskNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'title' => 'Call', 'notes' => '', 'due' => ''], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/lists/%40default/tasks') && $request->data() === ['title' => 'Call']);
});

it('marks a task done', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['id' => 't1', 'status' => 'completed'])]);

    (new GoogleTasksUpdateTaskNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_id' => 't1', 'status' => 'completed'], []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request->data() === ['status' => 'completed']);
});

it('reopens a task and clears its completion time', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['id' => 't1'])]);

    (new GoogleTasksUpdateTaskNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_id' => 't1', 'status' => 'needsAction'], []);

    Http::assertSent(fn ($request) => $request->body() === '{"status":"needsAction","completed":null}');
});

it('refuses a task update that changes nothing', function () {
    Http::fake();

    (new GoogleTasksUpdateTaskNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_id' => 't1'], []);
})->throws(InvalidArgumentException::class, 'at least one field');

it('deletes a task', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response('', 204)]);

    $output = (new GoogleTasksDeleteTaskNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_list_id' => 'l1', 'task_id' => 't/1'], []);

    expect($output)->toBe(['deleted' => true, 'task_id' => 't/1']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/lists/l1/tasks/t%2F1'));
});

it('surfaces a tasks api error', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid task list ID']], 400)]);

    (new GoogleTasksListTasksNode)->execute(Run::factory()->create(), [...TASKS_BASE, 'task_list_id' => 'bad'], []);
})->throws(RuntimeException::class, 'Google Tasks API error');

it('needs an access token', function () {
    (new GoogleTasksListTasksNode)->execute(Run::factory()->create(), [], []);
})->throws(RuntimeException::class, 'access_token');

it('reads only the tasks changed in the last day for the new task trigger', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => []])]);
    $node = new GoogleTasksListTasksNode;

    $node->execute(Run::factory()->create(), $node->triggerReadConfig(TASKS_BASE), []);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'updatedMin=2026-02-28T10:00:00.000Z') && str_contains($request->url(), 'maxResults=100'));
    Carbon::setTestNow();
});

it('lists every task as an action when no changed-since time is set', function () {
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => []])]);

    (new GoogleTasksListTasksNode)->execute(Run::factory()->create(), TASKS_BASE, []);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'updatedMin'));
});

it('lists tasks marked done lately, hidden ones included', function () {
    Carbon::setTestNow('2026-03-01 10:00:00');
    Http::fake(['tasks.googleapis.com/*' => Http::response(['items' => [['id' => 't1', 'status' => 'completed', 'completed' => '2026-03-01T09:00:00.000Z']]])]);
    $node = new GoogleTasksListCompletedTasksNode;

    $output = $node->execute(Run::factory()->create(), [...TASKS_BASE, 'completed_within_hours' => 2], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'showCompleted=true')
        && str_contains($request->url(), 'showHidden=true')
        && str_contains(urldecode($request->url()), 'completedMin=2026-03-01T08:00:00.000Z'));
    expect($node->triggerItems($output, []))->toHaveCount(1)
        ->and($node->triggerItemId($output['items'][0]))->toBe('t1:2026-03-01T09:00:00.000Z')
        ->and($node->triggerName())->toBe('Google Tasks: Task completed')
        ->and($node->triggerReadConfig([])['completed_within_hours'])->toBe(24);
    Carbon::setTestNow();
});

it('counts finishing a reopened task again as a new completion', function () {
    $node = new GoogleTasksListCompletedTasksNode;

    expect($node->triggerItemId(['id' => 't1', 'completed' => '2026-03-01T09:00:00.000Z']))
        ->not->toBe($node->triggerItemId(['id' => 't1', 'completed' => '2026-03-02T09:00:00.000Z']));
});
