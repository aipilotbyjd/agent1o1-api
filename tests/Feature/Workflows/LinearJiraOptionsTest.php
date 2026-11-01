<?php

use App\Exceptions\ConnectorException;
use App\Models\User;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\NodeOptionsService;
use App\Services\Workflows\NodeOptions\Sources\JiraOptions;
use App\Services\Workflows\NodeOptions\Sources\LinearOptions;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $config
 */
function dropdownQuery(array $config = [], ?string $search = null, ?string $cursor = null): NodeOptionsQuery
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return new NodeOptionsQuery($workspace, $owner, $config, 'test-token', $search, $cursor);
}

/**
 * Answer Linear's GraphQL endpoint, by text in the query.
 *
 * @param  array<string, array<string, mixed>>  $handlers
 */
function fakeLinearOptions(array $handlers): void
{
    Http::swap(new Factory);
    Http::fake(['api.linear.app/graphql' => function (Request $request) use ($handlers) {
        foreach ($handlers as $needle => $data) {
            if (str_contains((string) $request['query'], $needle)) {
                return Http::response(['data' => $data]);
            }
        }

        return Http::response(['errors' => [['message' => 'unexpected']]]);
    }]);
}

/**
 * Answer the Atlassian gateway: the account's sites, and Jira by path.
 *
 * @param  array<string, mixed>  $routes  "path prefix" => body
 */
function fakeJiraOptions(array $routes): void
{
    Http::swap(new Factory);
    Cache::flush();
    Http::fake(function (Request $request) use ($routes) {
        if (str_contains($request->url(), 'accessible-resources')) {
            return Http::response([['id' => 'cloud-1', 'url' => 'https://acme.atlassian.net', 'name' => 'acme'], ['id' => 'cloud-2', 'url' => 'https://beta.atlassian.net', 'name' => 'beta']]);
        }

        $path = preg_replace('~^/ex/jira/[^/]+/rest/(api/3|servicedeskapi)~', '', (string) parse_url($request->url(), PHP_URL_PATH));

        foreach ($routes as $prefix => $body) {
            if (str_starts_with((string) $path, $prefix)) {
                return Http::response($body);
            }
        }

        return Http::response(['errorMessages' => ["Nothing fakes {$path}"]], 404);
    });
}

/**
 * The options of a page as value => label.
 *
 * @return array<int|string, string>
 */
function dropdownLabels(object $page): array
{
    return collect($page->options)->mapWithKeys(fn ($option): array => [$option->value => $option->label])->all();
}

describe('linear dropdowns', function () {
    it('lists teams, with the key as the hint, and searches by name or key', function () {
        fakeLinearOptions(['teams(filter' => ['teams' => ['nodes' => [['id' => 't1', 'key' => 'ENG', 'name' => 'Engineering']], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cur-2']]]]);

        $page = app(LinearOptions::class)->load('linear.teams', dropdownQuery(search: 'eng', cursor: 'cur-1'));

        expect(dropdownLabels($page))->toBe(['t1' => 'Engineering'])
            ->and($page->options[0]->description)->toBe('ENG')
            ->and($page->nextCursor)->toBe('cur-2');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['variables']['after'] === 'cur-1'
            && $request['variables']['filter'] === ['or' => [['name' => ['containsIgnoreCase' => 'eng']], ['key' => ['containsIgnoreCase' => 'eng']]]]);
    });

    it('narrows statuses and labels to the node\'s team, given by name', function () {
        fakeLinearOptions([
            'teams(filter' => ['teams' => ['nodes' => [['id' => 't1']]]],
            'workflowStates(filter' => ['workflowStates' => ['nodes' => [['id' => 's1', 'name' => 'In Progress', 'type' => 'started']], 'pageInfo' => ['hasNextPage' => false]]],
            'issueLabels(filter' => ['issueLabels' => ['nodes' => [['id' => 'l1', 'name' => 'Bug', 'team' => null], ['id' => 'l2', 'name' => 'Auth', 'team' => ['name' => 'Engineering']]], 'pageInfo' => ['hasNextPage' => false]]],
        ]);

        $states = app(LinearOptions::class)->load('linear.states', dropdownQuery(['team' => 'Engineering']));
        $labels = app(LinearOptions::class)->load('linear.labels', dropdownQuery(['team' => 'Engineering']));

        expect(dropdownLabels($states))->toBe(['s1' => 'In Progress'])
            ->and($states->options[0]->description)->toBe('started')
            ->and(dropdownLabels($labels))->toBe(['l1' => 'Bug', 'l2' => 'Auth'])
            ->and($labels->options[0]->description)->toBe('Workspace')
            ->and($labels->options[1]->description)->toBe('Engineering');

        $filters = collect(Http::recorded()->all())->map(fn (array $pair): array => $pair[0]['variables']['filter'] ?? [])->all();

        // Statuses belong to one team; labels are the team's and the workspace's.
        expect($filters)->toContain(['team' => ['id' => ['eq' => 't1']]])
            ->and($filters)->toContain(['or' => [['team' => ['id' => ['eq' => 't1']]], ['team' => ['null' => true]]]]);
    });

    it('does not look a team up when the field is still a template, and lists every status', function () {
        fakeLinearOptions(['workflowStates(filter' => ['workflowStates' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]);

        app(LinearOptions::class)->load('linear.states', dropdownQuery(['team' => '{{ nodes.a.team }}']));

        expect(Http::recorded()->all())->toHaveCount(1);
    });

    it('lists the people who are still active, projects with their status, and initiatives', function () {
        fakeLinearOptions([
            'users(filter' => ['users' => ['nodes' => [['id' => 'u1', 'name' => 'Ada', 'displayName' => 'ada', 'email' => 'ada@acme.com']], 'pageInfo' => ['hasNextPage' => false]]],
            'projects(filter' => ['projects' => ['nodes' => [['id' => 'p1', 'name' => 'Website', 'status' => ['name' => 'Started']]], 'pageInfo' => ['hasNextPage' => false]]],
            'initiatives(filter' => ['initiatives' => ['nodes' => [['id' => 'i1', 'name' => 'Grow EMEA', 'status' => 'Active']], 'pageInfo' => ['hasNextPage' => false]]],
        ]);

        $users = app(LinearOptions::class)->load('linear.users', dropdownQuery());
        $projects = app(LinearOptions::class)->load('linear.projects', dropdownQuery());
        $initiatives = app(LinearOptions::class)->load('linear.initiatives', dropdownQuery());

        expect(dropdownLabels($users))->toBe(['u1' => 'Ada'])
            ->and($users->options[0]->description)->toBe('ada@acme.com')
            ->and($projects->options[0]->description)->toBe('Started')
            ->and(dropdownLabels($initiatives))->toBe(['i1' => 'Grow EMEA']);

        expect(Http::recorded()->first()[0]['variables']['filter'])->toBe(['active' => ['eq' => true]]);
    });

    it('hides what Linear said behind a fixed message', function () {
        Http::swap(new Factory);
        Http::fake(['api.linear.app/graphql' => Http::response(['errors' => [['message' => 'secret detail']]])]);

        expect(fn () => app(LinearOptions::class)->load('linear.teams', dropdownQuery()))->toThrow(ConnectorException::class, "Couldn't load options from Linear.");
    });

    it('says to reconnect when Linear rejects the account', function () {
        Http::swap(new Factory);
        Http::fake(['api.linear.app/graphql' => Http::response(['error' => 'x'], 401)]);

        expect(fn () => app(LinearOptions::class)->load('linear.teams', dropdownQuery()))->toThrow(ConnectorException::class, 'rejected the connected account');
    });
});

describe('jira dropdowns', function () {
    it('lists the sites of the account', function () {
        fakeJiraOptions([]);

        $page = app(JiraOptions::class)->load('jira.sites', dropdownQuery(search: 'be'));

        expect(dropdownLabels($page))->toBe(['cloud-2' => 'beta'])
            ->and($page->options[0]->description)->toBe('https://beta.atlassian.net');
    });

    it('reads the site the node names, or the first', function () {
        fakeJiraOptions(['/project/search' => ['values' => [['key' => 'APP', 'name' => 'App']], 'isLast' => true]]);

        app(JiraOptions::class)->load('jira.projects', dropdownQuery());
        expect(Http::recorded()->last()[0]->url())->toStartWith('https://api.atlassian.com/ex/jira/cloud-1/');

        fakeJiraOptions(['/project/search' => ['values' => [], 'isLast' => true]]);
        app(JiraOptions::class)->load('jira.projects', dropdownQuery(['site' => 'beta.atlassian.net']));
        expect(Http::recorded()->last()[0]->url())->toStartWith('https://api.atlassian.com/ex/jira/cloud-2/');
    });

    it('refuses a site the account cannot open', function () {
        fakeJiraOptions([]);

        expect(fn () => app(JiraOptions::class)->load('jira.projects', dropdownQuery(['site' => 'nowhere'])))->toThrow(ConnectorException::class, 'nowhere');
    });

    it('lists projects by key, and pages with Jira\'s own offset', function () {
        fakeJiraOptions(['/project/search' => ['values' => [['key' => 'APP', 'name' => 'App'], ['key' => 'OPS', 'name' => 'Operations']], 'isLast' => false]]);

        $page = app(JiraOptions::class)->load('jira.projects', dropdownQuery(search: 'ap', cursor: '50'));

        expect(dropdownLabels($page))->toBe(['APP' => 'App', 'OPS' => 'Operations'])
            ->and($page->nextCursor)->toBe('52');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/project/search') && str_contains($request->url(), 'startAt=50') && str_contains($request->url(), 'query=ap'));
    });

    it('lists the issue types of the node\'s project, or every type, and marks sub-tasks', function () {
        fakeJiraOptions([
            '/issue/createmeta/APP/issuetypes' => ['issueTypes' => [['id' => '1', 'name' => 'Bug'], ['id' => '2', 'name' => 'Sub-task', 'subtask' => true]]],
            '/issuetype' => [['id' => '3', 'name' => 'Task', 'description' => 'A task']],
        ]);

        $forProject = app(JiraOptions::class)->load('jira.issue_types', dropdownQuery(['project' => 'APP']));
        $all = app(JiraOptions::class)->load('jira.issue_types', dropdownQuery());

        expect(dropdownLabels($forProject))->toBe(['Bug' => 'Bug', 'Sub-task' => 'Sub-task'])
            ->and($forProject->options[1]->description)->toBe('Sub-task')
            ->and(dropdownLabels($all))->toBe(['Task' => 'Task']);
    });

    it('lists statuses of a project without repeats, or of the site', function () {
        fakeJiraOptions([
            '/project/APP/statuses' => [['name' => 'Bug', 'statuses' => [['name' => 'To Do', 'statusCategory' => ['name' => 'To Do']], ['name' => 'Done', 'statusCategory' => ['name' => 'Done']]]], ['name' => 'Task', 'statuses' => [['name' => 'To Do', 'statusCategory' => ['name' => 'To Do']]]]],
            '/status' => [['name' => 'Open']],
        ]);

        $forProject = app(JiraOptions::class)->load('jira.statuses', dropdownQuery(['project' => 'APP']));
        $all = app(JiraOptions::class)->load('jira.statuses', dropdownQuery(['project' => '{{ input.p }}']));

        expect(array_keys(dropdownLabels($forProject)))->toBe(['To Do', 'Done'])
            ->and(array_keys(dropdownLabels($all)))->toBe(['Open']);
    });

    it('lists people by account id, leaving out apps and people who left', function () {
        fakeJiraOptions(['/users/search' => [
            ['accountId' => 'a1', 'displayName' => 'Ada', 'emailAddress' => 'ada@acme.com', 'accountType' => 'atlassian', 'active' => true],
            ['accountId' => 'a2', 'displayName' => 'Bot', 'accountType' => 'app', 'active' => true],
            ['accountId' => 'a3', 'displayName' => 'Gone', 'accountType' => 'atlassian', 'active' => false],
        ], '/user/search' => [['accountId' => 'a1', 'displayName' => 'Ada', 'accountType' => 'atlassian', 'active' => true]]]);

        $everyone = app(JiraOptions::class)->load('jira.users', dropdownQuery());
        $found = app(JiraOptions::class)->load('jira.users', dropdownQuery(search: 'ada'));

        expect(dropdownLabels($everyone))->toBe(['a1' => 'Ada'])
            ->and($everyone->options[0]->description)->toBe('ada@acme.com')
            ->and($everyone->nextCursor)->toBeNull()
            ->and(dropdownLabels($found))->toBe(['a1' => 'Ada']);
    });

    it('lists labels, saved filters with their JQL, groups and link types', function () {
        fakeJiraOptions([
            '/label' => ['values' => ['backend', 'frontend', 'urgent'], 'isLast' => false],
            '/filter/search' => ['values' => [['id' => '10042', 'name' => 'Mine', 'jql' => 'assignee = currentUser()']], 'isLast' => true],
            '/group/bulk' => ['values' => [['groupId' => 'g1', 'name' => 'jira-developers']], 'isLast' => true],
            '/issueLinkType' => ['issueLinkTypes' => [['name' => 'Blocks', 'inward' => 'is blocked by', 'outward' => 'blocks']]],
        ]);

        $labels = app(JiraOptions::class)->load('jira.labels', dropdownQuery(search: 'end'));
        $filters = app(JiraOptions::class)->load('jira.filters', dropdownQuery());
        $groups = app(JiraOptions::class)->load('jira.groups', dropdownQuery());
        $links = app(JiraOptions::class)->load('jira.link_types', dropdownQuery());

        expect(array_keys(dropdownLabels($labels)))->toBe(['backend', 'frontend'])
            ->and($labels->nextCursor)->toBe('3')
            ->and(dropdownLabels($filters))->toBe(['10042' => 'Mine'])
            ->and($filters->options[0]->description)->toBe('assignee = currentUser()')
            ->and(dropdownLabels($groups))->toBe(['jira-developers' => 'jira-developers'])
            ->and($links->options[0]->description)->toBe('blocks / is blocked by');
    });

    it('lists the moves open to the issue, and needs the key first', function () {
        fakeJiraOptions(['/issue/APP-1/transitions' => ['transitions' => [['id' => '11', 'name' => 'Start work', 'to' => ['name' => 'In Progress']]]]]);

        $page = app(JiraOptions::class)->load('jira.transitions', dropdownQuery(['issue' => 'APP-1']));

        expect(dropdownLabels($page))->toBe(['Start work' => 'Start work'])
            ->and($page->options[0]->description)->toBe('To In Progress')
            ->and(fn () => app(JiraOptions::class)->load('jira.transitions', dropdownQuery(['issue' => ''])))->toThrow(ValidationException::class);
    });

    it('lists service desks and the request types of the chosen one', function () {
        fakeJiraOptions([
            '/servicedesk/1/requesttype' => ['values' => [['id' => '10', 'name' => 'Report a problem', 'description' => 'd']], 'isLastPage' => true],
            '/servicedesk' => ['values' => [['id' => '1', 'projectKey' => 'HELP', 'projectName' => 'Help']], 'isLastPage' => false],
        ]);

        $desks = app(JiraOptions::class)->load('jira.service_desks', dropdownQuery());
        $types = app(JiraOptions::class)->load('jira.request_types', dropdownQuery(['service_desk' => '1']));

        expect(dropdownLabels($desks))->toBe(['1' => 'Help'])
            ->and($desks->nextCursor)->toBe('1')
            ->and(dropdownLabels($types))->toBe(['10' => 'Report a problem'])
            ->and(fn () => app(JiraOptions::class)->load('jira.request_types', dropdownQuery(['service_desk' => 'x/../y'])))->toThrow(ValidationException::class);
    });

    it('answers every source it claims, and the options service knows them', function () {
        $sources = app(NodeOptionsService::class)->sourceKeys();

        foreach ([...(new LinearOptions)->sources(), ...(new JiraOptions)->sources()] as $source) {
            expect($sources)->toContain($source);
        }
    });
});
