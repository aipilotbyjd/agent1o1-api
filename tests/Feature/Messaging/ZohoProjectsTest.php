<?php

use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Jobs\Triggers\SyncTriggerSubscription;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\ZohoProjects\AbstractZohoProjectsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsAddTaskCommentNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsCreateTaskNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsGetTaskNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListChangedTasksNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListNewBugsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsListProjectsNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsLogTimeNode;
use App\Nodes\Integrations\ZohoProjects\ZohoProjectsUpdateTaskNode;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\ZohoProjectsOptions;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

const ZPROJ_ID = '1234500000012345';

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function zprojData(array $data = []): array
{
    return ['region' => 'us', 'client_id' => 'cid', 'api_key' => 'csecret', 'refresh_token' => 'rtoken', 'portal_id' => '2000', ...$data];
}

/**
 * A run with a connected Zoho Projects account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function zprojRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'zoho_projects') ?? Connector::factory()->create(['key' => 'zoho_projects']))->id,
        'data' => zprojData($data),
        'is_default' => true,
    ]);

    return $run;
}

/**
 * Answer Zoho Projects and the Zoho login. A key is "METHOD /path" under
 * /api/v3/portal/2000; the value is the JSON body (null is a 204), or a closure
 * returning a response. A route nobody answers is a 404.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeZohoProjects(array $routes): void
{
    Http::swap(new Factory);
    Http::fake(['*zoho*' => function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/oauth/v2/token') {
            return Http::response(['access_token' => '1000.zoho-session', 'api_domain' => 'https://www.zohoapis.com', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $key = $request->method().' '.preg_replace('~^/api/v3/portal/2000~', '', $path);
        $route = array_key_exists($key, $routes) ? $key : collect(array_keys($routes))->first(fn (string $pattern): bool => Str::is($pattern, $key));

        if ($route === null) {
            return Http::response(['message' => "Nothing fakes {$key}"], 404);
        }

        $answer = value($routes[$route], $request);

        return $answer instanceof GuzzleHttp\Promise\PromiseInterface ? $answer : ($answer === null ? Http::response(null, 204) : Http::response($answer));
    }]);
}

function zprojRequest(string $route): Request
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method().' '.preg_replace('~^/api/v3/portal/2000~', '', (string) parse_url($pair[0]->url(), PHP_URL_PATH)) === $route));

    expect($found)->toHaveCount(1);

    return $found[0][0];
}

/**
 * @return array<string, mixed>
 */
function zprojBody(string $route): array
{
    return (array) json_decode(zprojRequest($route)->body(), true);
}

/**
 * @return array<string, string>
 */
function zprojQuery(string $route): array
{
    parse_str((string) parse_url(zprojRequest($route)->url(), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function zprojTask(string $id = ZPROJ_ID, array $extra = []): array
{
    return ['id' => $id, 'name' => 'Draft the launch email', 'status' => ['name' => 'Open'], 'priority' => 'High', 'project' => ['id' => '1234500000000001', 'name' => 'Launch'], 'created_time' => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:sP'), 'last_updated_time' => now()->subMinutes(5)->utc()->format('Y-m-d\TH:i:sP'), ...$extra];
}

describe('zoho projects nodes', function () {
    it('registers the nodes under the zoho_projects connector', function () {
        $registry = app(NodeRegistry::class);
        $types = array_filter(array_column($registry->placeableTypes(), 'type'), fn (string $type): bool => str_starts_with($type, 'zoho_projects_'));

        expect(count($types))->toBeGreaterThanOrEqual(17);

        foreach ($types as $type) {
            expect($registry->resolve($type))->toBeInstanceOf(AbstractZohoProjectsNode::class)->and($registry->resolve($type)->category())->toBe('zoho_projects');
        }
    });

    it('creates a task under the portal with the token in a header', function () {
        fakeZohoProjects(['POST /projects/1234500000000001/tasks' => ['tasks' => [zprojTask()]]]);

        $output = (new ZohoProjectsCreateTaskNode)->execute(zprojRun(), ['project_id' => '1234500000000001', 'name' => ' Draft the launch email ', 'owner_id' => '1234500000000077', 'priority' => 'High', 'due_date' => '2026-03-31T10:00:00Z'], []);

        $request = zprojRequest('POST /projects/1234500000000001/tasks');

        expect($output)->toMatchArray(['id' => ZPROJ_ID, 'status' => 'Open', 'project' => 'Launch'])
            ->and($request->url())->toStartWith('https://projectsapi.zoho.com/api/v3/portal/2000/projects/1234500000000001/tasks')
            ->and($request->header('Authorization')[0])->toBe('Zoho-oauthtoken 1000.zoho-session')
            ->and(zprojBody('POST /projects/1234500000000001/tasks'))->toMatchArray(['name' => 'Draft the launch email', 'priority' => 'High', 'end_date' => '2026-03-31', 'owners_and_work' => ['owners' => [['add' => ['1234500000000077']]]]]);
    });

    it('refuses a connection without the portal id, and a bad id', function () {
        fakeZohoProjects([]);

        expect(fn () => (new ZohoProjectsGetTaskNode)->execute(zprojRun(['portal_id' => '']), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID], []))->toThrow(InvalidArgumentException::class, 'portal id')
            ->and(fn () => (new ZohoProjectsGetTaskNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => '../x'], []))->toThrow(InvalidArgumentException::class);
    });

    it('says a missing task is not found', function () {
        fakeZohoProjects(['GET /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID => fn () => Http::response(['message' => 'gone'], 404)]);

        expect((new ZohoProjectsGetTaskNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID], []))->toBe(['found' => false, 'id' => ZPROJ_ID]);
    });

    it('lists projects across pages with index and range', function () {
        $page = 0;
        fakeZohoProjects(['GET /projects' => function () use (&$page) {
            $page++;

            return $page === 1 ? ['projects' => array_map(fn (int $n): array => ['id' => (string) (1234500000000000 + $n), 'name' => "P{$n}"], range(1, 200))] : ['projects' => [['id' => '1234500000009999', 'name' => 'Last']]];
        }]);

        $output = (new ZohoProjectsListProjectsNode)->execute(zprojRun(), ['limit' => 300], []);

        expect($output['count'])->toBe(201)->and($page)->toBe(2);
    });

    it('updates only what is filled in and clamps the percentage', function () {
        fakeZohoProjects(['PATCH /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID => ['tasks' => [zprojTask()]]]);

        (new ZohoProjectsUpdateTaskNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID, 'percent_complete' => 150], []);

        expect(zprojBody('PATCH /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID))->toBe(['percent_complete' => 100])
            ->and(fn () => (new ZohoProjectsUpdateTaskNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID], []))->toThrow(InvalidArgumentException::class);
    });

    it('adds a comment and logs hours as hours and minutes', function () {
        fakeZohoProjects(['POST /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID.'/comments' => ['comments' => [['id' => '55']]], 'POST /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID.'/timelogs' => []]);

        $comment = (new ZohoProjectsAddTaskCommentNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID, 'content' => 'On it'], []);
        $log = (new ZohoProjectsLogTimeNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID, 'hours' => '2.5', 'date' => '2026-03-02'], []);

        expect($comment['id'])->toBe('55')
            ->and($log)->toBe(['logged' => true, 'task_id' => ZPROJ_ID, 'hours' => 2.5])
            ->and(zprojBody('POST /projects/'.ZPROJ_ID.'/tasks/'.ZPROJ_ID.'/timelogs'))->toMatchArray(['date' => '2026-03-02', 'daily_log_hours' => '02:30', 'bill_status' => 'Billable'])
            ->and(fn () => (new ZohoProjectsLogTimeNode)->execute(zprojRun(), ['project_id' => ZPROJ_ID, 'task_id' => ZPROJ_ID, 'hours' => '30'], []))->toThrow(InvalidArgumentException::class);
    });

    it('explains an error with a hint', function () {
        fakeZohoProjects(['GET /projects' => fn () => Http::response(['message' => 'INVALID_OAUTHTOKEN'], 401)]);

        expect(fn () => (new ZohoProjectsListProjectsNode)->execute(zprojRun(), [], []))->toThrow(RuntimeException::class, 'Reconnect Zoho Projects');
    });
});

describe('zoho projects options', function () {
    it('lists projects and users a page at a time, filtering by the search', function () {
        fakeZohoProjects(['GET /projects' => ['projects' => [['id' => '1', 'name' => 'Launch'], ['id' => '2', 'name' => 'Website']]], 'GET /users' => ['users' => [['id' => '9', 'name' => 'Ada', 'email' => 'ada@acme.com']]]]);
        $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
        $source = app(ZohoProjectsOptions::class);

        $projects = $source->load('zoho_projects.projects', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', 'web', credentialData: zprojData()));
        $users = $source->load('zoho_projects.users', new NodeOptionsQuery($workspace, User::factory()->create(), [], 'csecret', credentialData: zprojData()));

        expect($projects->toArray()['options'])->toBe([['value' => '2', 'label' => 'Website']])
            ->and($users->toArray()['options'])->toBe([['value' => '9', 'label' => 'Ada', 'description' => 'ada@acme.com']]);
    });
});

/**
 * A published workflow whose first step is a Projects reader switched on as a trigger.
 */
function zprojTrigger(string $nodeType = 'zoho_projects_list_new_tasks'): Trigger
{
    Queue::fake([FireTriggerEvent::class, PollTrigger::class]);
    fakeZohoProjects([]);

    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $credential = ConnectorCredential::factory()->forWorkspace($workspace)
        ->forConnector(Connector::query()->firstWhere('key', 'zoho_projects') ?? Connector::factory()->create(['key' => 'zoho_projects']))
        ->create(['data' => zprojData(), 'created_by' => $owner->id]);

    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'start', 'type' => $nodeType, 'config' => ['credential_id' => $credential->id, 'activate' => true]],
            ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
        ],
        'edges' => [['from' => 'start', 'to' => 'next']],
    ]);
    $workflow->fresh()->publishVersion(publisher: $owner);

    $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
    SyncTriggerSubscription::dispatchSync($trigger->id);

    return $trigger->fresh();
}

describe('zoho projects triggers', function () {
    it('polls new tasks: a baseline first, then one run for each', function () {
        $trigger = zprojTrigger();
        $poll = fn (array $ids) => fakeZohoProjects(['GET /tasks' => ['tasks' => array_map(fn (string $id): array => zprojTask($id), $ids)]]);

        $poll(['1234500000000001']);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll(['1234500000000003', '1234500000000002', '1234500000000001']);
        dispatch_sync(new PollTrigger($trigger->fresh()));

        expect($trigger->events()->where('status', 'queued')->get()->pluck('payload.id')->sort()->values()->all())->toBe(['1234500000000002', '1234500000000003']);
    });

    it('keeps changed tasks within the look-back, reads times in milliseconds, and makes every change its own event', function () {
        fakeZohoProjects(['GET /tasks' => ['tasks' => [zprojTask('1', ['last_updated_time' => now()->subMinutes(5)->getTimestampMs()]), zprojTask('2', ['last_updated_time' => now()->subDays(5)->utc()->format('Y-m-d\TH:i:sP')])]]]);
        $node = new ZohoProjectsListChangedTasksNode;

        $output = $node->execute(zprojRun(), ['changed_within_minutes' => 60], []);

        expect(array_column($output['tasks'], 'id'))->toBe(['1'])
            ->and(zprojQuery('GET /tasks'))->toMatchArray(['sort_by' => 'DESC(last_updated_time)'])
            ->and($node->triggerItemId(['id' => 1, 'updated_at' => 'a']))->not->toBe($node->triggerItemId(['id' => 1, 'updated_at' => 'b']));
    });

    it('reads bugs from the issues list', function () {
        fakeZohoProjects(['GET /issues' => ['issues' => [['id' => '7', 'title' => 'Checkout fails', 'created_time' => now()->subMinutes(3)->utc()->format('Y-m-d\TH:i:sP')]]]]);

        $output = (new ZohoProjectsListNewBugsNode)->execute(zprojRun(), [], []);

        expect($output['bugs'][0])->toMatchArray(['id' => '7', 'title' => 'Checkout fails']);
    });
});
