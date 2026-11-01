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
use App\Nodes\Integrations\Asana\AbstractAsanaNode;
use App\Nodes\Integrations\Asana\AsanaAddCommentNode;
use App\Nodes\Integrations\Asana\AsanaCompleteTaskNode;
use App\Nodes\Integrations\Asana\AsanaCreateTaskNode;
use App\Nodes\Integrations\Asana\AsanaListCommentsNode;
use App\Nodes\Integrations\Asana\AsanaListCompletedTasksNode;
use App\Nodes\Integrations\Asana\AsanaListNewTasksNode;
use App\Nodes\Integrations\Asana\AsanaListProjectsNode;
use App\Nodes\Integrations\Asana\AsanaListTasksNode;
use App\Nodes\Integrations\Asana\AsanaListUpdatedTasksNode;
use App\Nodes\Integrations\Asana\AsanaMoveTaskToSectionNode;
use App\Nodes\Integrations\Asana\AsanaSearchTasksNode;
use App\Nodes\Integrations\Asana\AsanaUpdateTaskNode;
use App\Services\Triggers\Push\AsanaSource;
use App\Services\Triggers\Push\WatchManager;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\AsanaOptions;
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
function asanaConfig(array $config = []): array
{
    return ['access_token' => 'asana_pat', ...$config];
}

/**
 * Answer Asana. A key is "METHOD /path" under /api/1.0 (a trailing * takes any
 * id); the value is the JSON body, or a closure returning a response. A route
 * nobody answers is a 404, so a node that calls something unexpected fails the
 * test.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeAsana(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['app.asana.com/*' => function (Request $request) use ($routes) {
        $key = $request->method().' '.preg_replace('~^/api/1\.0~', '', (string) parse_url($request->url(), PHP_URL_PATH));
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['errors' => [['message' => "Nothing fakes {$key}"]]], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : Http::response($answer ?? ['data' => []]);
    }]);
}

/**
 * The request made to a route.
 */
function asanaRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/1\.0~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * The `data` of the body sent to a route.
 *
 * @return array<string, mixed>
 */
function asanaData(string $route): array
{
    return (array) (json_decode(asanaRequest($route)->body(), true)['data'] ?? []);
}

/**
 * @return array<string, string>
 */
function asanaQuery(string $route): array
{
    parse_str((string) parse_url(asanaRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function asanaTask(string $gid = '1001', array $extra = []): array
{
    return ['gid' => $gid, 'name' => 'Write the email', 'notes' => 'Draft it', 'completed' => false, 'completed_at' => null, 'created_at' => now()->subMinutes(10)->toIso8601String(), 'modified_at' => now()->subMinutes(5)->toIso8601String(), 'due_on' => '2026-03-31', 'permalink_url' => 'https://app.asana.com/0/1/'.$gid, 'assignee' => ['gid' => '7', 'name' => 'Ada', 'email' => 'ada@example.com'], 'projects' => [['gid' => '55', 'name' => 'Launch']], 'memberships' => [['section' => ['name' => 'To do']]], 'tags' => [['name' => 'Urgent']], 'num_subtasks' => 2, ...$extra];
}

describe('asana nodes', function () {
    it('registers the nodes under the asana connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'asana_'));

        expect(count($types))->toBeGreaterThanOrEqual(30);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractAsanaNode::class)->and($registry->resolve($type)->category())->toBe('asana');
        }
    });

    it('creates a task in a project and section, wrapped in data, with a bearer token', function () {
        fakeAsana(['POST /tasks' => ['data' => asanaTask()]]);

        $output = (new AsanaCreateTaskNode)->execute(Run::factory()->create(), asanaConfig(['name' => ' Write the email ', 'notes' => 'Draft it', 'project_id' => '55', 'section_id' => '66', 'assignee' => 'me', 'due_on' => '2026-03-31T09:00:00Z', 'tag_ids' => ['9']]), []);

        expect($output)->toMatchArray(['id' => '1001', 'name' => 'Write the email', 'assignee' => 'Ada', 'assignee_email' => 'ada@example.com', 'projects' => ['Launch'], 'sections' => ['To do'], 'tags' => ['Urgent'], 'url' => 'https://app.asana.com/0/1/1001'])
            ->and(asanaData('POST /tasks'))->toBe(['name' => 'Write the email', 'notes' => 'Draft it', 'projects' => ['55'], 'memberships' => [['project' => '55', 'section' => '66']], 'assignee' => 'me', 'due_on' => '2026-03-31', 'tags' => ['9']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer asana_pat'));
    });

    it('uses the account\'s first workspace for a task outside a project', function () {
        fakeAsana(['GET /users/me' => ['data' => ['workspaces' => [['gid' => '12']]]], 'POST /tasks' => ['data' => asanaTask()]]);

        (new AsanaCreateTaskNode)->execute(Run::factory()->create(), asanaConfig(['name' => 'Loose task']), []);

        expect(asanaData('POST /tasks'))->toBe(['name' => 'Loose task', 'workspace' => '12']);
    });

    it('checks ids, assignees and changes before calling Asana', function () {
        $node = new AsanaCreateTaskNode;
        $run = Run::factory()->create();
        fakeAsana([]);

        expect(fn () => $node->execute($run, asanaConfig(['name' => 'x', 'project_id' => '../projects']), []))->toThrow(InvalidArgumentException::class, 'must be an Asana id')
            ->and(fn () => $node->execute($run, asanaConfig(['name' => 'x', 'project_id' => '55', 'assignee' => 'someone else']), []))->toThrow(InvalidArgumentException::class, 'Assign to "me"')
            ->and(fn () => $node->execute($run, asanaConfig(['project_id' => '55']), []))->toThrow(InvalidArgumentException::class, 'The name is required')
            ->and(fn () => (new AsanaUpdateTaskNode)->execute($run, asanaConfig(['task_id' => '1001']), []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');
        Http::assertNothingSent();
    });

    it('updates only what is filled in, and completes or reopens', function () {
        fakeAsana(['PUT /tasks/1001' => ['data' => asanaTask('1001', ['completed' => true, 'completed_at' => '2026-03-01T10:00:00.000Z'])]]);
        $run = Run::factory()->create();

        $updated = (new AsanaUpdateTaskNode)->execute($run, asanaConfig(['task_id' => '1001', 'assignee' => 'ada@example.com', 'completed' => 'done']), []);
        expect($updated['completed'])->toBeTrue()->and(asanaData('PUT /tasks/1001'))->toBe(['assignee' => 'ada@example.com', 'completed' => true]);

        fakeAsana(['PUT /tasks/1001' => ['data' => ['gid' => '1001', 'name' => 'x', 'completed' => false]]]);
        $reopened = (new AsanaCompleteTaskNode)->execute($run, asanaConfig(['task_id' => '1001', 'state' => 'open']), []);

        expect($reopened)->toBe(['id' => '1001', 'name' => 'x', 'completed' => false, 'completed_at' => null])
            ->and(asanaData('PUT /tasks/1001'))->toBe(['completed' => false]);
    });

    it('pages through a project\'s tasks by offset, and keeps the completed ones when asked', function () {
        fakeAsana(['GET /projects/55/tasks' => fn (Request $request) => str_contains($request->url(), 'offset=off1')
            ? ['data' => [asanaTask('1003', ['completed' => true])], 'next_page' => null]
            : ['data' => [asanaTask('1001'), asanaTask('1002', ['completed' => true])], 'next_page' => ['offset' => 'off1']]]);

        $output = (new AsanaListTasksNode)->execute(Run::factory()->create(), asanaConfig(['project_id' => '55', 'status' => 'done', 'limit' => 150, 'modified_after' => '2026-03-01T00:00:00Z']), []);

        expect(array_column($output['tasks'], 'id'))->toBe(['1002', '1003']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'modified_since=2026-03-01T00:00:00+00:00') || str_contains($request->url(), 'modified_since='));
        expect(fn () => (new AsanaListTasksNode)->execute(Run::factory()->create(), asanaConfig(), []))->toThrow(InvalidArgumentException::class, 'Pick a project, a section or an assignee');
    });

    it('lists comments only, not the activity around them', function () {
        fakeAsana(['GET /tasks/1001/stories' => ['data' => [
            ['gid' => 's1', 'type' => 'system', 'resource_subtype' => 'assigned', 'text' => 'assigned to Ada'],
            ['gid' => 's2', 'type' => 'comment', 'resource_subtype' => 'comment_added', 'text' => 'Looks good', 'created_at' => '2026-03-01T10:00:00.000Z', 'created_by' => ['name' => 'Ada']],
        ]]]);

        $output = (new AsanaListCommentsNode)->execute(Run::factory()->create(), asanaConfig(['task_id' => '1001']), []);

        expect($output['comments'])->toBe([['id' => 's2', 'text' => 'Looks good', 'created_at' => '2026-03-01T10:00:00.000Z', 'author' => 'Ada']]);
    });

    it('comments, moves a task to a section, and lists projects of the first workspace', function () {
        fakeAsana([
            'POST /tasks/1001/stories' => ['data' => ['gid' => 's3', 'text' => 'Thanks', 'created_at' => '2026-03-01T10:00:00.000Z', 'created_by' => ['name' => 'Ada']]],
            'POST /sections/66/addTask' => ['data' => []],
            'GET /users/me' => ['data' => ['workspaces' => [['gid' => '12']]]],
            'GET /projects' => ['data' => [['gid' => '55', 'name' => 'Launch', 'archived' => false]]],
        ]);
        $run = Run::factory()->create();

        $comment = (new AsanaAddCommentNode)->execute($run, asanaConfig(['task_id' => '1001', 'text' => 'Thanks']), []);
        $moved = (new AsanaMoveTaskToSectionNode)->execute($run, asanaConfig(['task_id' => '1001', 'section_id' => '66']), []);
        $projects = (new AsanaListProjectsNode)->execute($run, asanaConfig(), []);

        expect($comment)->toMatchArray(['id' => 's3', 'text' => 'Thanks', 'author' => 'Ada'])
            ->and($moved)->toBe(['moved' => true, 'task_id' => '1001', 'section_id' => '66'])
            ->and(asanaData('POST /sections/66/addTask'))->toBe(['task' => '1001'])
            ->and($projects['projects'][0])->toMatchArray(['id' => '55', 'name' => 'Launch'])
            ->and(asanaQuery('GET /projects'))->toMatchArray(['workspace' => '12', 'archived' => 'false']);
    });

    it('says a search needs a paid plan, using what Asana said', function () {
        fakeAsana(['GET /workspaces/12/tasks/search' => fn () => Http::response(['errors' => [['message' => 'Search is available to premium users only']]], 402)]);

        expect(fn () => (new AsanaSearchTasksNode)->execute(Run::factory()->create(), asanaConfig(['workspace_id' => '12', 'text' => 'launch']), []))
            ->toThrow(RuntimeException::class, 'Asana error: Search is available to premium users only. This needs a paid Asana plan.');
    });
});

describe('asana dropdowns', function () {
    it('lists projects of the chosen workspace with the next page, and sections of a project', function () {
        fakeAsana([
            'GET /projects' => ['data' => [['gid' => '55', 'name' => 'Launch'], ['gid' => '56', 'name' => 'Hiring']], 'next_page' => ['offset' => 'nx']],
            'GET /projects/55/sections' => ['data' => [['gid' => '66', 'name' => 'To do']]],
        ]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $query = fn (array $config, ?string $search = null): NodeOptionsQuery => new NodeOptionsQuery($workspace, User::factory()->create(), $config, 'asana_pat', $search);
        $source = app(AsanaOptions::class);

        expect($source->load('asana.projects', $query(['workspace_id' => '12']))->toArray())->toBe(['options' => [['value' => '55', 'label' => 'Launch'], ['value' => '56', 'label' => 'Hiring']], 'next_cursor' => 'nx'])
            ->and(array_column($source->load('asana.projects', $query(['workspace_id' => '12'], 'hir'))->toArray()['options'], 'label'))->toBe(['Hiring'])
            ->and($source->load('asana.sections', $query(['project_id' => '55']))->toArray()['options'])->toBe([['value' => '66', 'label' => 'To do']])
            ->and($source->load('asana.sections', $query([]))->toArray()['options'])->toBe([]);
    });
});

/**
 * A published workflow whose first step is an Asana task reader switched on as a
 * trigger, with its subscription synced against a faked Asana.
 *
 * @param  array<string, mixed>  $nodeConfig
 * @param  array<string, mixed>  $routes
 */
function asanaTrigger(string $nodeType = 'asana_list_new_tasks', array $nodeConfig = [], array $routes = [], bool $sync = true): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeAsana($routes);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'asana') ?? Connector::factory()->create(['key' => 'asana']))
        ->create(['data' => ['api_key' => 'asana_pat'], 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true, 'project_id' => '55', ...$nodeConfig]],
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

describe('asana triggers', function () {
    it('polls new tasks: a baseline first, then one run for each', function () {
        $trigger = asanaTrigger(sync: false);
        $poll = fn (array $ids) => fakeAsana(['GET /projects/55/tasks' => ['data' => array_map(fn (string $id): array => asanaTask($id), $ids)]]);

        $poll(['1001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['1003', '1002', '1001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['1002', '1003']);
    });

    it('tells created, completed and changed tasks apart', function () {
        fakeAsana(['GET /projects/55/tasks' => ['data' => [
            asanaTask('1', ['created_at' => now()->subMinutes(10)->toIso8601String(), 'modified_at' => now()->subMinutes(1)->toIso8601String()]),
            asanaTask('2', ['created_at' => now()->subDays(3)->toIso8601String(), 'completed' => true, 'completed_at' => now()->subMinutes(2)->toIso8601String(), 'modified_at' => now()->subMinutes(2)->toIso8601String()]),
            asanaTask('3', ['created_at' => now()->subDays(3)->toIso8601String(), 'completed' => true, 'completed_at' => now()->subDays(2)->toIso8601String(), 'modified_at' => now()->subMinutes(3)->toIso8601String()]),
        ]]]);
        $run = Run::factory()->create();
        $config = asanaConfig(['project_id' => '55', 'changed_within_minutes' => 60]);

        $created = (new AsanaListNewTasksNode)->execute($run, $config, []);
        $completed = (new AsanaListCompletedTasksNode)->execute($run, $config, []);
        $changed = (new AsanaListUpdatedTasksNode)->execute($run, $config, []);

        expect(array_column($created['tasks'], 'id'))->toBe(['1'])
            ->and(array_column($completed['tasks'], 'id'))->toBe(['2'])
            ->and(array_column($changed['tasks'], 'id'))->toBe(['1', '2', '3']);

        $node = new AsanaListUpdatedTasksNode;
        expect($node->triggerItemId(['id' => '1', 'modified_at' => 'a']))->not->toBe($node->triggerItemId(['id' => '1', 'modified_at' => 'b']))
            ->and((new AsanaListNewTasksNode)->triggerItemId(['id' => '1', 'modified_at' => 'a']))->toBe('1');
    });
});

describe('asana instant delivery', function () {
    it('creates a webhook on the project, answers Asana\'s handshake, and starts a poll when a change arrives', function () {
        $trigger = asanaTrigger(routes: ['POST /webhooks' => ['data' => ['gid' => 'wh1', 'active' => true]]]);
        $watch = ProviderWatch::query()->sole();

        expect($watch->resource_key)->toBe('55')
            ->and($watch->external_id)->toBe('wh1')
            ->and($trigger->subscription->status)->toBe(SubscriptionStatus::Active)
            ->and(asanaData('POST /webhooks'))->toBe(['resource' => '55', 'target' => url('/api/hooks/apps/asana').'?token='.$watch->secret]);

        test()->postJson('/api/hooks/apps/asana?token='.$watch->secret, [], ['X-Hook-Secret' => 'handshake-secret'])->assertOk()->assertHeader('X-Hook-Secret', 'handshake-secret');
        Queue::assertNotPushed(PollTrigger::class);

        test()->postJson('/api/hooks/apps/asana?token='.$watch->secret, ['events' => []])->assertOk();
        Queue::assertNotPushed(PollTrigger::class);

        $body = ['events' => [['action' => 'added', 'resource' => ['gid' => '1001', 'resource_type' => 'task']]]];
        test()->postJson('/api/hooks/apps/asana?token='.$watch->secret, $body)->assertOk();
        test()->postJson('/api/hooks/apps/asana?token='.$watch->secret, $body)->assertOk();

        Queue::assertPushed(PollTrigger::class, 1);
    });

    it('does not register without a numeric project', function () {
        $trigger = asanaTrigger(nodeConfig: ['project_id' => '{{ trigger.project }}'], routes: ['POST /webhooks' => ['data' => ['gid' => 'wh1']]]);

        expect($trigger->subscription)->toBeNull()->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('refuses a wrong or missing token, even with a handshake header, and is closed while the switch is off', function () {
        asanaTrigger(routes: ['POST /webhooks' => ['data' => ['gid' => 'wh1']]]);
        $watch = ProviderWatch::query()->sole();

        test()->postJson('/api/hooks/apps/asana?token=nope', [], ['X-Hook-Secret' => 'x'])->assertUnauthorized();
        test()->postJson('/api/hooks/apps/asana', ['events' => [['action' => 'added']]])->assertUnauthorized();
        config(['triggers.push.asana.enabled' => false]);
        test()->postJson('/api/hooks/apps/asana?token='.$watch->secret, ['events' => [['action' => 'added']]])->assertNotFound();

        Queue::assertNotPushed(PollTrigger::class);
    });

    it('says so when Asana refuses the webhook, and drops the watch', function () {
        $trigger = asanaTrigger(routes: ['POST /webhooks' => fn () => Http::response(['errors' => [['message' => 'Could not complete activation handshake']]], 400)]);

        expect($trigger->subscription->status)->toBe(SubscriptionStatus::Failed)->and($trigger->subscription->last_error)->toContain('400')->and(ProviderWatch::query()->count())->toBe(0);
    });

    it('makes the webhook again when Asana turned it off or lost it, and removes it with the watch', function () {
        asanaTrigger(routes: ['POST /webhooks' => ['data' => ['gid' => 'wh1']]]);
        $watch = ProviderWatch::query()->sole();
        $source = app(AsanaSource::class);

        fakeAsana(['GET /webhooks/wh1' => ['data' => ['gid' => 'wh1', 'active' => false]], 'POST /webhooks' => ['data' => ['gid' => 'wh2']], 'DELETE /webhooks/wh1' => ['data' => []]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        expect($watch->fresh()->external_id)->toBe('wh2');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && parse_url($request->url(), PHP_URL_PATH) === '/api/1.0/webhooks/wh1');

        fakeAsana(['GET /webhooks/wh2' => ['data' => ['gid' => 'wh2', 'active' => true]]]);
        app(WatchManager::class)->renew($source, $watch->fresh());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');

        fakeAsana(['DELETE /webhooks/wh2' => ['data' => []]]);
        app(WatchManager::class)->remove($source, $watch->fresh());
        expect(ProviderWatch::query()->count())->toBe(0);
    });
});
