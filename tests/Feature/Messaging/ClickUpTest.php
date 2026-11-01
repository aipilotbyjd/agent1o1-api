<?php

use App\Enums\Triggers\SubscriptionStatus;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\ClickUp\AbstractClickUpNode;
use App\Nodes\Integrations\ClickUp\ClickUpAddCommentNode;
use App\Nodes\Integrations\ClickUp\ClickUpAddTagNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateChecklistNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateTaskNode;
use App\Nodes\Integrations\ClickUp\ClickUpCreateTimeEntryNode;
use App\Nodes\Integrations\ClickUp\ClickUpFilterTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpListTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpListUpdatedTasksNode;
use App\Nodes\Integrations\ClickUp\ClickUpSetCustomFieldNode;
use App\Nodes\Integrations\ClickUp\ClickUpUpdateTaskNode;
use App\Services\Triggers\Push\ClickUpSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ClickUpOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function clickupConfig(array $config = []): array
{
    return ['access_token' => 'pk_test', ...$config];
}

/**
 * Answer ClickUp. A key is "METHOD /path" under /api/v2 (a trailing * takes any
 * id); the value is the JSON body, or a closure returning a response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the
 * test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeClickUp(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['api.clickup.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['err' => "Nothing fakes {$key}", 'ECODE' => 'TEST_001'], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? []);
    }]);
}

/**
 * The request made to a route.
 */
function clickupRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v2~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function clickupBody(string $route): array
{
    return (array) json_decode(clickupRequest($route)->body(), true);
}

/**
 * The query string of a route's request, undecoded, as a list of name=value parts.
 *
 * @return list<string>
 */
function clickupQuery(string $route): array
{
    return array_map('urldecode', array_values(array_filter(explode('&', (string) parse_url(clickupRequest($route)->url(), PHP_URL_QUERY)))));
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function clickupTask(string $id = '86abc1', array $extra = []): array
{
    return ['id' => $id, 'name' => 'Write the email', 'text_content' => 'Draft it', 'status' => ['status' => 'in progress'], 'priority' => ['priority' => 'high'], 'assignees' => [['id' => 7, 'username' => 'Ada', 'email' => 'ada@example.com']], 'tags' => [['name' => 'urgent']], 'due_date' => '1772100000000', 'date_created' => (string) now()->subMinutes(10)->getTimestampMs(), 'date_updated' => (string) now()->subMinutes(5)->getTimestampMs(), 'url' => 'https://app.clickup.com/t/'.$id, 'list' => ['id' => '901', 'name' => 'Backlog'], 'space' => ['id' => '55'], ...$extra];
}

describe('clickup nodes', function () {
    it('registers the nodes under the clickup connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'clickup_'));

        expect(count($types))->toBeGreaterThanOrEqual(25);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractClickUpNode::class)->and($registry->resolve($type)->category())->toBe('clickup');
        }
    });

    it('creates a task with the token as it is, dates in milliseconds and a numeric priority', function () {
        fakeClickUp(['POST /list/901/task' => clickupTask()]);

        $output = (new ClickUpCreateTaskNode)->execute(Run::factory()->create(), clickupConfig(['list_id' => '901', 'name' => ' Write the email ', 'status' => 'to do', 'priority' => 'urgent', 'assignee_ids' => ['7', '8'], 'tags' => ['urgent'], 'due_date' => '2026-02-26T10:00:00Z', 'parent_id' => '86zzz']), []);

        expect($output)->toMatchArray(['id' => '86abc1', 'name' => 'Write the email', 'status' => 'in progress', 'priority' => 'high', 'assignees' => ['Ada'], 'assignee_ids' => ['7'], 'tags' => ['urgent'], 'due_at' => '2026-02-26T10:00:00+00:00', 'list_id' => '901', 'list' => 'Backlog'])
            ->and(clickupBody('POST /list/901/task'))->toBe(['name' => 'Write the email', 'status' => 'to do', 'priority' => 1, 'assignees' => [7, 8], 'tags' => ['urgent'], 'due_date' => 1772100000000, 'parent' => '86zzz']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'pk_test'));
    });

    it('checks ids and changes before calling ClickUp', function () {
        fakeClickUp([]);
        $run = Run::factory()->create();
        $node = new ClickUpCreateTaskNode;

        expect(fn () => $node->execute($run, clickupConfig(['list_id' => '9/../1', 'name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'must be a ClickUp id')
            ->and(fn () => $node->execute($run, clickupConfig(['name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'The list is required')
            ->and(fn () => $node->execute($run, clickupConfig(['list_id' => '901', 'name' => 'x', 'assignee_ids' => ['7', 'me']]), []))->toThrow(InvalidArgumentException::class, 'must be a ClickUp id')
            ->and(fn () => (new ClickUpUpdateTaskNode)->execute($run, clickupConfig(['task_id' => '86abc1']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing')
            ->and(fn () => (new ClickUpUpdateTaskNode)->execute($run, clickupConfig(['task_id' => '../team', 'name' => 'x']), []))->toThrow(InvalidArgumentException::class, 'not a ClickUp task id');
        Http::assertNothingSent();
    });

    it('updates only what is filled in, adding and removing assignees', function () {
        fakeClickUp(['PUT /task/86abc1' => clickupTask()]);

        (new ClickUpUpdateTaskNode)->execute(Run::factory()->create(), clickupConfig(['task_id' => '86abc1', 'status' => 'complete', 'add_assignee_ids' => ['7'], 'remove_assignee_ids' => '8', 'archived' => 'no']), []);

        expect(clickupBody('PUT /task/86abc1'))->toBe(['status' => 'complete', 'assignees' => ['add' => [7], 'rem' => [8]], 'archived' => false]);
    });

    it('lists a list\'s tasks page by page with the filters as ClickUp spells them', function () {
        $page = fn (int $from, int $count): array => ['tasks' => array_map(fn (int $i): array => clickupTask("t{$i}"), range($from, $from + $count - 1))];
        fakeClickUp(['GET /list/901/task' => fn (Request $request) => str_contains($request->url(), 'page=1') ? $page(101, 20) : $page(1, 100)]);

        $output = (new ClickUpListTasksNode)->execute(Run::factory()->create(), clickupConfig(['list_id' => '901', 'limit' => 110, 'statuses' => ['in progress', 'to do'], 'updated_after' => '2026-03-01T00:00:00Z', 'include_closed' => true]), []);

        expect($output['count'])->toBe(110);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'statuses[]=in progress') && str_contains(urldecode($request->url()), 'statuses[]=to do') && str_contains($request->url(), 'date_updated_gt=1772323200000') && str_contains($request->url(), 'include_closed=true'));
    });

    it('finds tasks across a workspace by list, assignee and due date', function () {
        fakeClickUp(['GET /team/12/task' => ['tasks' => [clickupTask('a'), clickupTask('b')]]]);

        $output = (new ClickUpFilterTasksNode)->execute(Run::factory()->create(), clickupConfig(['workspace_id' => '12', 'list_ids' => ['901'], 'assignee_ids' => ['7'], 'due_before' => '2026-03-31T00:00:00Z', 'limit' => 1]), []);

        expect($output['count'])->toBe(1)
            ->and(clickupQuery('GET /team/12/task'))->toContain('list_ids[]=901', 'assignees[]=7', 'reverse=true');
    });

    it('comments, tags and sets a custom field', function () {
        fakeClickUp(['POST /task/86abc1/comment' => ['id' => 458, 'date' => '1772100000000'], 'POST /task/86abc1/tag/needs%20review' => [], 'POST /task/86abc1/field/ab12-cd' => []]);
        $run = Run::factory()->create();

        $comment = (new ClickUpAddCommentNode)->execute($run, clickupConfig(['task_id' => '86abc1', 'text' => 'Looks good', 'assignee_id' => '7']), []);
        $tag = (new ClickUpAddTagNode)->execute($run, clickupConfig(['task_id' => '86abc1', 'tag' => 'needs review']), []);
        $field = (new ClickUpSetCustomFieldNode)->execute($run, clickupConfig(['task_id' => '86abc1', 'field_id' => 'ab12-cd', 'value' => '42']), []);

        expect($comment)->toBe(['id' => '458', 'task_id' => '86abc1', 'date' => '2026-02-26T10:00:00+00:00'])
            ->and(clickupBody('POST /task/86abc1/comment'))->toBe(['comment_text' => 'Looks good', 'assignee' => 7, 'notify_all' => false])
            ->and($tag['added'])->toBeTrue()
            ->and($field['set'])->toBeTrue()->and(clickupBody('POST /task/86abc1/field/ab12-cd'))->toBe(['value' => 42])
            ->and(fn () => (new ClickUpSetCustomFieldNode)->execute($run, clickupConfig(['task_id' => '86abc1', 'field_id' => 'a/b', 'value' => '1']), []))->toThrow(InvalidArgumentException::class, 'not a ClickUp field id');
    });

    it('adds a checklist with items, and logs time in milliseconds', function () {
        fakeClickUp(['POST /task/86abc1/checklist' => ['checklist' => ['id' => 'chk-1', 'name' => 'Launch']], 'POST /checklist/chk-1/checklist_item' => ['checklist' => ['items' => []]], 'POST /team/12/time_entries' => ['data' => ['id' => '99']]]);
        $run = Run::factory()->create();

        $checklist = (new ClickUpCreateChecklistNode)->execute($run, clickupConfig(['task_id' => '86abc1', 'name' => 'Launch', 'items' => ['Write', ' ', 'Send']]), []);
        $time = (new ClickUpCreateTimeEntryNode)->execute($run, clickupConfig(['workspace_id' => '12', 'task_id' => '86abc1', 'minutes' => 30, 'started_at' => '2026-03-01T09:00:00Z']), []);

        expect($checklist)->toBe(['id' => 'chk-1', 'name' => 'Launch', 'task_id' => '86abc1', 'items_added' => 2])
            ->and($time)->toBe(['id' => '99', 'task_id' => '86abc1', 'minutes' => 30])
            ->and(clickupBody('POST /team/12/time_entries'))->toBe(['tid' => '86abc1', 'start' => 1772355600000, 'duration' => 1800000])
            ->and(fn () => (new ClickUpCreateTimeEntryNode)->execute($run, clickupConfig(['workspace_id' => '12', 'task_id' => '86abc1', 'minutes' => 0]), []))->toThrow(InvalidArgumentException::class, 'between 1 and 1440');
    });

    it('says what ClickUp said when it refuses', function () {
        fakeClickUp(['POST /list/901/task' => fn () => Http::response(['err' => 'Status not found', 'ECODE' => 'CRTSK_004'], 400)]);

        expect(fn () => (new ClickUpCreateTaskNode)->execute(Run::factory()->create(), clickupConfig(['list_id' => '901', 'name' => 'x', 'status' => 'nope']), []))->toThrow(RuntimeException::class, 'ClickUp error: Status not found (CRTSK_004).');
    });
});

describe('clickup dropdowns', function () {
    it('narrows workspaces to spaces to folders to lists, and uses the folder when there is one', function () {
        fakeClickUp([
            'GET /team' => ['teams' => [['id' => '12', 'name' => 'Acme']]],
            'GET /team/12/space' => ['spaces' => [['id' => '55', 'name' => 'Marketing']]],
            'GET /space/55/folder' => ['folders' => [['id' => '77', 'name' => 'Campaigns']]],
            'GET /folder/77/list' => ['lists' => [['id' => '901', 'name' => 'Q2']]],
            'GET /space/55/list' => ['lists' => [['id' => '902', 'name' => 'Loose']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config = []): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'pk_test');
        $source = app(ClickUpOptions::class);

        expect($source->load('clickup.workspaces', $query())->toArray()['options'])->toBe([['value' => '12', 'label' => 'Acme']])
            ->and($source->load('clickup.spaces', $query(['workspace_id' => '12']))->toArray()['options'])->toBe([['value' => '55', 'label' => 'Marketing']])
            ->and($source->load('clickup.folders', $query(['space_id' => '55']))->toArray()['options'])->toBe([['value' => '77', 'label' => 'Campaigns']])
            ->and($source->load('clickup.lists', $query(['space_id' => '55', 'folder_id' => '77']))->toArray()['options'])->toBe([['value' => '901', 'label' => 'Q2']])
            ->and($source->load('clickup.lists', $query(['space_id' => '55']))->toArray()['options'])->toBe([['value' => '902', 'label' => 'Loose']])
            ->and($source->load('clickup.spaces', $query())->toArray()['options'])->toBe([]);
    });
});

/**
 * A published workflow whose first step is a ClickUp task reader switched on as a
 * trigger, with its subscription synced against a faked ClickUp.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function clickupTrigger(string $nodeType = 'clickup_list_new_tasks', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeClickUp($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'clickup') ?? Connector::factory()->create(['key' => 'clickup']))
        ->create(['data' => ['api_key' => 'pk_test'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, 'workspace_id' => '12', 'list_id' => '901', ...$nodeConfig]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

    if ($sync) {
        SyncTriggerSubscription::dispatchSync($trigger->id);
    }

    return $trigger->fresh();
}

describe('clickup triggers', function () {
    it('polls new tasks: a baseline first, then one run for each', function () {
        $trigger = clickupTrigger(sync: false);
        $poll = fn (array $ids) => fakeClickUp(['GET /list/901/task' => ['tasks' => array_map(fn (string $id): array => clickupTask($id), $ids)]]);

        $poll(['t1']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['t3', 't2', 't1']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['t2', 't3']);
    });

    it('makes every change of a task its own event, and asks for the right time filter', function () {
        fakeClickUp(['GET /list/901/task' => ['tasks' => [clickupTask('t1')]]]);
        $node = new ClickUpListUpdatedTasksNode;

        $node->execute(Run::factory()->create(), clickupConfig(['list_id' => '901']), []);

        expect($node->triggerItemId(['id' => 't1', 'updated_ms' => '1']))->not->toBe($node->triggerItemId(['id' => 't1', 'updated_ms' => '2']))
            ->and(implode('&', clickupQuery('GET /list/901/task')))->toContain('date_updated_gt=')->not->toContain('date_created_gt=');
    });
});

describe('clickup instant delivery', function () {
    it('creates a webhook on the list for the kind of change, and starts a poll when it arrives', function () {
        $trigger = clickupTrigger('clickup_list_updated_tasks', routes: ['POST /team/12/webhook' => ['id' => 'wh-1', 'webhook' => ['id' => 'wh-1']]]);
        $watch = ProviderWatch::query()->sole();
        $sent = clickupBody('POST /team/12/webhook');

        expect($watch->resource_key)->toBe('changed:12:901')
            ->and($watch->external_id)->toBe('wh-1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($sent['endpoint'])->toBe(url('/api/hooks/apps/clickup').'?token='.$watch->secret)
            ->and($sent['list_id'])->toBe(901)
            ->and($sent['events'])->toContain('taskUpdated', 'taskStatusUpdated', 'taskCommentPosted');

        $body = ['event' => 'taskStatusUpdated', 'task_id' => '86abc1', 'history_items' => [['id' => 'h1']]];
        test()->postJson('/api/hooks/apps/clickup?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/clickup?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('listens to taskCreated alone for new tasks, and does not register without a workspace and list', function () {
        clickupTrigger(routes: ['POST /team/12/webhook' => ['id' => 'wh-1']]);

        expect(ProviderWatch::query()->sole()->resource_key)->toBe('created:12:901')->and(clickupBody('POST /team/12/webhook')['events'])->toBe(['taskCreated']);

        $unmapped = clickupTrigger(nodeConfig: ['list_id' => '{{ trigger.list }}'], routes: ['POST /team/12/webhook' => ['id' => 'wh-2']]);

        expect($unmapped->subscription)->toBeNull();
    });

    it('refuses a wrong or missing token and is closed while the switch is off', function () {
        clickupTrigger(routes: ['POST /team/12/webhook' => ['id' => 'wh-1']]);
        $watch = ProviderWatch::query()->sole();
        $body = ['event' => 'taskCreated', 'task_id' => 't1'];

        test()->postJson('/api/hooks/apps/clickup?token=nope', $body)->assertUnauthorized();
        test()->postJson('/api/hooks/apps/clickup', $body)->assertUnauthorized();
        config(['triggers.push.clickup.enabled' => false]);
        test()->postJson('/api/hooks/apps/clickup?token='.$watch->secret, $body)->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when ClickUp refuses the webhook, and drops the watch', function () {
        $trigger = clickupTrigger(routes: ['POST /team/12/webhook' => fn () => Http::response(['err' => 'Team not authorized', 'ECODE' => 'OAUTH_023'], 401)]);

        expect($trigger->subscription->status)->not->toBe(SubscriptionStatus::Active)->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when ClickUp suspended or lost it, and removes it with the watch', function () {
        clickupTrigger(routes: ['POST /team/12/webhook' => ['id' => 'wh-1']]);
        $watch = ProviderWatch::query()->sole();
        $source = app(ClickUpSource::class);

        fakeClickUp(['GET /team/12/webhook' => ['webhooks' => [['id' => 'wh-1', 'health' => ['status' => 'suspended']]]], 'POST /team/12/webhook' => ['id' => 'wh-2'], 'DELETE /webhook/wh-1' => []]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh-2');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && parse_url($request->url(), PHP_URL_PATH) === '/api/v2/webhook/wh-1');

        fakeClickUp(['GET /team/12/webhook' => ['webhooks' => [['id' => 'wh-2', 'health' => ['status' => 'active']]]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeClickUp(['DELETE /webhook/wh-2' => []]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
