<?php

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Jobs\Triggers\PollTrigger;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Nodes\Integrations\Jira\AbstractJiraNode;
use App\Nodes\Integrations\Jira\JiraAddAttachmentNode;
use App\Nodes\Integrations\Jira\JiraAddCommentNode;
use App\Nodes\Integrations\Jira\JiraAddUserToGroupNode;
use App\Nodes\Integrations\Jira\JiraAddUserToIssueNode;
use App\Nodes\Integrations\Jira\JiraCreateCustomerRequestNode;
use App\Nodes\Integrations\Jira\JiraCreateGroupNode;
use App\Nodes\Integrations\Jira\JiraCreateIssueLinkNode;
use App\Nodes\Integrations\Jira\JiraCreateIssueNode;
use App\Nodes\Integrations\Jira\JiraCreateProjectNode;
use App\Nodes\Integrations\Jira\JiraDeleteCommentNode;
use App\Nodes\Integrations\Jira\JiraDeleteIssueLinkNode;
use App\Nodes\Integrations\Jira\JiraDeleteIssueNode;
use App\Nodes\Integrations\Jira\JiraDeleteProjectNode;
use App\Nodes\Integrations\Jira\JiraDownloadAttachmentNode;
use App\Nodes\Integrations\Jira\JiraGetCustomerRequestNode;
use App\Nodes\Integrations\Jira\JiraGetEditMetadataNode;
use App\Nodes\Integrations\Jira\JiraGetIssueLinksNode;
use App\Nodes\Integrations\Jira\JiraGetIssueNode;
use App\Nodes\Integrations\Jira\JiraGetMyIssuesNode;
use App\Nodes\Integrations\Jira\JiraGetMyPermissionsNode;
use App\Nodes\Integrations\Jira\JiraGetMyRecentActivityNode;
use App\Nodes\Integrations\Jira\JiraGetMyselfNode;
use App\Nodes\Integrations\Jira\JiraGetProjectNode;
use App\Nodes\Integrations\Jira\JiraGetRequestTypeFieldsNode;
use App\Nodes\Integrations\Jira\JiraGetRequestTypesNode;
use App\Nodes\Integrations\Jira\JiraListCommentsNode;
use App\Nodes\Integrations\Jira\JiraListCustomerRequestsNode;
use App\Nodes\Integrations\Jira\JiraListFieldsNode;
use App\Nodes\Integrations\Jira\JiraListFiltersNode;
use App\Nodes\Integrations\Jira\JiraListGroupsNode;
use App\Nodes\Integrations\Jira\JiraListIssueLinkTypesNode;
use App\Nodes\Integrations\Jira\JiraListIssuesNode;
use App\Nodes\Integrations\Jira\JiraListIssueTypesNode;
use App\Nodes\Integrations\Jira\JiraListNewIssuesNode;
use App\Nodes\Integrations\Jira\JiraListPrioritiesNode;
use App\Nodes\Integrations\Jira\JiraListProjectsNode;
use App\Nodes\Integrations\Jira\JiraListServiceDesksNode;
use App\Nodes\Integrations\Jira\JiraListTransitionsNode;
use App\Nodes\Integrations\Jira\JiraListUpdatedIssuesNode;
use App\Nodes\Integrations\Jira\JiraListUsersNode;
use App\Nodes\Integrations\Jira\JiraRemoveUserFromGroupNode;
use App\Nodes\Integrations\Jira\JiraSearchIssuesNode;
use App\Nodes\Integrations\Jira\JiraTransitionIssueNode;
use App\Nodes\Integrations\Jira\JiraUpdateCommentNode;
use App\Nodes\Integrations\Jira\JiraUpdateIssueNode;
use App\Nodes\Integrations\Jira\JiraUpdateProjectNode;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const JIRA_CLOUD = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
const JIRA_ACCOUNT = '5b10ac8d82e05b22cc7d4ef5';
const JIRA_API = 'https://api.atlassian.com/ex/jira/'.JIRA_CLOUD.'/rest/api/3';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function jiraConfig(array $config = []): array
{
    return ['access_token' => 'atl_test', ...$config];
}

/**
 * Answer Jira Cloud. Each key is "METHOD path" (the path after the API root,
 * matched as the start of the request's path), tried in order; the value is
 * the JSON to return, or a closure taking the request, or a ready response. The
 * site list is always the one site. A request nobody answers is a 404, so a
 * node that asks for something unexpected fails the test. Each call starts
 * from a clean client and an empty cache.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeJira(array $routes): void
{
    Http::swap(new Factory);
    Cache::flush();

    Http::fake(function (Request $request) use ($routes) {
        if (str_contains($request->url(), 'accessible-resources')) {
            return Http::response([['id' => JIRA_CLOUD, 'url' => 'https://acme.atlassian.net', 'name' => 'acme', 'scopes' => []], ['id' => 'other-cloud', 'url' => 'https://beta.atlassian.net', 'name' => 'beta']]);
        }

        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $path = preg_replace('~^/ex/jira/[^/]+/rest/(api/3|servicedeskapi)~', '', $path) ?? $path;

        foreach ($routes as $key => $response) {
            [$method, $prefix] = explode(' ', $key, 2);

            if ($method === $request->method() && str_starts_with($path, $prefix)) {
                $answer = value($response, $request);

                return $answer instanceof PromiseInterface ? $answer : Http::response($answer);
            }
        }

        return Http::response(['errorMessages' => ["Nothing fakes {$request->method()} {$path}"]], 404);
    });
}

/**
 * What the node sent to Jira (not the site list): method, path, query, the
 * JSON body decoded, and the body as sent.
 *
 * @return list<array{method: string, path: string, query: array<string, string>, body: mixed, raw: string}>
 */
function jiraSent(): array
{
    $sent = [];

    foreach (Http::recorded()->all() as [$request]) {
        if (str_contains($request->url(), 'accessible-resources')) {
            continue;
        }

        $path = preg_replace('~^/ex/jira/[^/]+/rest/(api/3|servicedeskapi)~', '', (string) parse_url($request->url(), PHP_URL_PATH)) ?? '';
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        $sent[] = ['method' => $request->method(), 'path' => $path, 'query' => $query, 'body' => $request->isJson() ? $request->data() : null, 'raw' => $request->body()];
    }

    return $sent;
}

/**
 * The one request that matches, so a test names exactly what it checks.
 *
 * @return array{method: string, path: string, query: array<string, string>, body: mixed, raw: string}
 */
function jiraRequest(string $method, string $path): array
{
    $found = array_values(array_filter(jiraSent(), fn (array $sent): bool => $sent['method'] === $method && $sent['path'] === $path));

    expect($found)->toHaveCount(1, "Expected one {$method} {$path}");

    return $found[0];
}

/**
 * A Jira issue as the API returns it.
 *
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function jiraIssue(string $key = 'APP-1', array $fields = []): array
{
    return [
        'id' => (string) (10000 + (int) substr($key, strrpos($key, '-') + 1)),
        'key' => $key,
        'fields' => [
            'summary' => 'Login fails',
            'description' => ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Steps to reproduce']]]]],
            'status' => ['name' => 'In Progress', 'statusCategory' => ['name' => 'In Progress']],
            'priority' => ['name' => 'High'],
            'issuetype' => ['name' => 'Bug', 'subtask' => false],
            'project' => ['key' => 'APP', 'name' => 'App'],
            'assignee' => ['displayName' => 'Ada', 'accountId' => JIRA_ACCOUNT, 'emailAddress' => 'ada@acme.com'],
            'reporter' => ['displayName' => 'Bob', 'accountId' => 'bob-id'],
            'labels' => ['frontend', 'auth'],
            'components' => [['name' => 'Web']],
            'fixVersions' => [['name' => '1.2']],
            'parent' => ['key' => 'APP-100', 'fields' => ['summary' => 'Epic']],
            'duedate' => '2026-03-10',
            'resolution' => null,
            'created' => '2026-03-01T10:00:00.000+0000',
            'updated' => '2026-03-01T11:00:00.000+0000',
            ...$fields,
        ],
    ];
}

it('registers every jira node with its own type under the jira category', function () {
    $registry = app(NodeRegistry::class);

    $expected = [
        JiraListProjectsNode::class => ['jira_list_projects', ActionEffect::Read],
        JiraGetProjectNode::class => ['jira_get_project', ActionEffect::Read],
        JiraCreateProjectNode::class => ['jira_create_project', ActionEffect::External],
        JiraUpdateProjectNode::class => ['jira_update_project', ActionEffect::Write],
        JiraDeleteProjectNode::class => ['jira_delete_project', ActionEffect::Destructive],
        JiraCreateIssueNode::class => ['jira_create_issue', ActionEffect::External],
        JiraGetIssueNode::class => ['jira_get_issue', ActionEffect::Read],
        JiraUpdateIssueNode::class => ['jira_update_issue', ActionEffect::Write],
        JiraDeleteIssueNode::class => ['jira_delete_issue', ActionEffect::Destructive],
        JiraListTransitionsNode::class => ['jira_list_transitions', ActionEffect::Read],
        JiraTransitionIssueNode::class => ['jira_transition_issue', ActionEffect::Write],
        JiraSearchIssuesNode::class => ['jira_search_issues', ActionEffect::Read],
        JiraListIssuesNode::class => ['jira_list_issues', ActionEffect::Read],
        JiraListNewIssuesNode::class => ['jira_list_new_issues', ActionEffect::Read],
        JiraListUpdatedIssuesNode::class => ['jira_list_updated_issues', ActionEffect::Read],
        JiraAddCommentNode::class => ['jira_add_comment', ActionEffect::External],
        JiraListCommentsNode::class => ['jira_list_comments', ActionEffect::Read],
        JiraUpdateCommentNode::class => ['jira_update_comment', ActionEffect::Write],
        JiraDeleteCommentNode::class => ['jira_delete_comment', ActionEffect::Destructive],
        JiraAddAttachmentNode::class => ['jira_add_attachment', ActionEffect::External],
        JiraDownloadAttachmentNode::class => ['jira_download_attachment', ActionEffect::Read],
        JiraListFieldsNode::class => ['jira_list_fields', ActionEffect::Read],
        JiraGetEditMetadataNode::class => ['jira_get_edit_metadata', ActionEffect::Read],
        JiraListIssueTypesNode::class => ['jira_list_issue_types', ActionEffect::Read],
        JiraListPrioritiesNode::class => ['jira_list_priorities', ActionEffect::Read],
        JiraListFiltersNode::class => ['jira_list_filters', ActionEffect::Read],
        JiraListUsersNode::class => ['jira_list_users', ActionEffect::Read],
        JiraAddUserToIssueNode::class => ['jira_add_user_to_issue', ActionEffect::Write],
        JiraGetMyselfNode::class => ['jira_get_myself', ActionEffect::Read],
        JiraGetMyIssuesNode::class => ['jira_get_my_issues', ActionEffect::Read],
        JiraGetMyRecentActivityNode::class => ['jira_get_my_recent_activity', ActionEffect::Read],
        JiraGetMyPermissionsNode::class => ['jira_get_my_permissions', ActionEffect::Read],
        JiraListGroupsNode::class => ['jira_list_groups', ActionEffect::Read],
        JiraCreateGroupNode::class => ['jira_create_group', ActionEffect::External],
        JiraAddUserToGroupNode::class => ['jira_add_user_to_group', ActionEffect::Write],
        JiraRemoveUserFromGroupNode::class => ['jira_remove_user_from_group', ActionEffect::Destructive],
        JiraListIssueLinkTypesNode::class => ['jira_list_issue_link_types', ActionEffect::Read],
        JiraCreateIssueLinkNode::class => ['jira_create_issue_link', ActionEffect::Write],
        JiraDeleteIssueLinkNode::class => ['jira_delete_issue_link', ActionEffect::Destructive],
        JiraGetIssueLinksNode::class => ['jira_get_issue_links', ActionEffect::Read],
        JiraListServiceDesksNode::class => ['jira_list_service_desks', ActionEffect::Read],
        JiraGetRequestTypesNode::class => ['jira_get_request_types', ActionEffect::Read],
        JiraGetRequestTypeFieldsNode::class => ['jira_get_request_type_fields', ActionEffect::Read],
        JiraCreateCustomerRequestNode::class => ['jira_create_customer_request', ActionEffect::External],
        JiraGetCustomerRequestNode::class => ['jira_get_customer_request', ActionEffect::Read],
        JiraListCustomerRequestsNode::class => ['jira_list_customer_requests', ActionEffect::Read],
    ];

    foreach ($expected as $class => [$type, $effect]) {
        $node = $registry->resolve($type);

        expect($node)->toBeInstanceOf($class)
            ->and($node)->toBeInstanceOf(AbstractJiraNode::class)
            ->and($node->type())->toBe($type)
            ->and($node->category())->toBe('jira')
            ->and($node->effect([]))->toBe($effect)
            ->and(array_keys($node->configSchema()['properties'])[0])->toBe('credential_id')
            ->and($node->configSchema()['properties'])->toHaveKey('site');
    }

    expect($expected)->toHaveCount(46);
});

describe('the site and errors', function () {
    it('uses the account\'s first site unless a site is named, by id, address or name', function () {
        fakeJira(['GET /myself' => ['accountId' => JIRA_ACCOUNT, 'displayName' => 'Ada']]);
        $node = new JiraGetMyselfNode;
        $run = Run::factory()->create();

        $node->execute($run, jiraConfig(), []);
        expect(Http::recorded()->last()[0]->url())->toStartWith('https://api.atlassian.com/ex/jira/'.JIRA_CLOUD.'/');

        Http::swap(new Factory);
        fakeJira(['GET /myself' => ['accountId' => 'b']]);
        $node->execute($run, jiraConfig(['site' => 'https://beta.atlassian.net/']), []);
        expect(Http::recorded()->last()[0]->url())->toStartWith('https://api.atlassian.com/ex/jira/other-cloud/');

        fakeJira(['GET /myself' => ['accountId' => 'b']]);
        $node->execute($run, jiraConfig(['site' => 'beta.atlassian.net']), []);
        expect(Http::recorded()->last()[0]->url())->toStartWith('https://api.atlassian.com/ex/jira/other-cloud/');
    });

    it('asks which sites the account has only once', function () {
        fakeJira(['GET /myself' => ['accountId' => 'a']]);
        $node = new JiraGetMyselfNode;
        $run = Run::factory()->create();

        $node->execute($run, jiraConfig(), []);
        $node->execute($run, jiraConfig(), []);

        $siteCalls = collect(Http::recorded()->all())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'accessible-resources'));

        expect($siteCalls)->toHaveCount(1);
    });

    it('refuses a site the account cannot open', function () {
        fakeJira([]);

        (new JiraGetMyselfNode)->execute(Run::factory()->create(), jiraConfig(['site' => 'nowhere.atlassian.net']), []);
    })->throws(InvalidArgumentException::class, 'nowhere.atlassian.net');

    it('says so when the account has no Jira site', function () {
        Http::swap(new Factory);
        Cache::flush();
        Http::fake(['api.atlassian.com/oauth/token/accessible-resources' => Http::response([])]);

        (new JiraGetMyselfNode)->execute(Run::factory()->create(), jiraConfig(), []);
    })->throws(RuntimeException::class, 'no Jira site');

    it('reports Jira\'s own words, both the general and the per-field messages', function () {
        fakeJira(['POST /issue' => Http::response(['errorMessages' => ['Project is required'], 'errors' => ['summary' => 'You must specify a summary']], 400)]);

        (new JiraCreateIssueNode)->execute(Run::factory()->create(), jiraConfig(['project' => 'APP', 'issue_type' => '10001', 'summary' => 'x']), []);
    })->throws(RuntimeException::class, 'Project is required; summary: You must specify a summary');

    it('falls back to the status when Jira says nothing', function () {
        fakeJira(['GET /myself' => Http::response('', 503)]);

        (new JiraGetMyselfNode)->execute(Run::factory()->create(), jiraConfig(), []);
    })->throws(RuntimeException::class, 'HTTP 503');
});

describe('projects', function () {
    $project = ['id' => '10000', 'key' => 'APP', 'name' => 'App', 'description' => 'The app', 'projectTypeKey' => 'software', 'style' => 'next-gen', 'lead' => ['displayName' => 'Ada', 'accountId' => JIRA_ACCOUNT]];

    it('lists, gets, creates, updates and deletes projects', function () use ($project) {
        fakeJira([
            'GET /project/search' => ['values' => [$project], 'isLast' => true],
            'GET /project/APP' => $project,
            'GET /myself' => ['accountId' => JIRA_ACCOUNT],
            'POST /project' => ['id' => '10000', 'key' => 'APP', 'self' => 'x'],
            'PUT /project/APP' => $project,
            'DELETE /project/APP' => Http::response(null, 204),
        ]);
        $run = Run::factory()->create();

        $listed = (new JiraListProjectsNode)->execute($run, jiraConfig(['query' => 'app']), []);
        $got = (new JiraGetProjectNode)->execute($run, jiraConfig(['project' => 'APP']), []);
        $created = (new JiraCreateProjectNode)->execute($run, jiraConfig(['key' => 'app', 'name' => 'App', 'template' => 'scrum']), []);
        $updated = (new JiraUpdateProjectNode)->execute($run, jiraConfig(['project' => 'APP', 'name' => 'App 2']), []);
        $deleted = (new JiraDeleteProjectNode)->execute($run, jiraConfig(['project' => 'APP', 'enable_undo' => false]), []);

        expect($listed['projects'][0])->toMatchArray(['key' => 'APP', 'name' => 'App', 'type' => 'software', 'lead' => 'Ada', 'lead_id' => JIRA_ACCOUNT])
            ->and(jiraRequest('GET', '/project/search')['query'])->toMatchArray(['query' => 'app', 'expand' => 'description,lead'])
            ->and($got['key'])->toBe('APP')
            ->and($created['key'])->toBe('APP')
            ->and(jiraRequest('POST', '/project')['body'])->toBe([
                'key' => 'APP', 'name' => 'App', 'projectTypeKey' => 'software', 'projectTemplateKey' => 'com.pyxis.greenhopper.jira:gh-simplified-agility-scrum',
                'leadAccountId' => JIRA_ACCOUNT, 'assigneeType' => 'PROJECT_LEAD',
            ])
            ->and($updated['key'])->toBe('APP')
            ->and(jiraRequest('PUT', '/project/APP')['body'])->toBe(['name' => 'App 2'])
            ->and($deleted)->toBe(['deleted' => true, 'key' => 'APP'])
            ->and(jiraRequest('DELETE', '/project/APP')['query'])->toMatchArray(['enableUndo' => 'false']);
    });

    it('refuses a project key Jira would not take', function () {
        (new JiraCreateProjectNode)->execute(Run::factory()->create(), jiraConfig(['key' => '1app', 'name' => 'x', 'template' => 'kanban']), []);
    })->throws(InvalidArgumentException::class, 'valid project key');

    it('does not let a project key walk the request path', function () {
        (new JiraGetProjectNode)->execute(Run::factory()->create(), jiraConfig(['project' => '../admin']), []);
    })->throws(InvalidArgumentException::class, 'project key');
});

describe('creating and reading issues', function () {
    it('creates an issue: markdown becomes ADF, a person is found by email, and the issue comes back shaped', function () {
        fakeJira([
            'GET /user/search' => [['accountId' => JIRA_ACCOUNT, 'displayName' => 'Ada', 'emailAddress' => 'ada@acme.com', 'accountType' => 'atlassian', 'active' => true]],
            'POST /issue' => ['id' => '10001', 'key' => 'APP-1'],
            'GET /issue/APP-1' => jiraIssue(),
        ]);

        $output = (new JiraCreateIssueNode)->execute(Run::factory()->create(), jiraConfig([
            'project' => 'APP', 'issue_type' => 'Bug', 'summary' => 'Login fails', 'description' => "## Steps\n\n- open the page\n- click **login**",
            'priority' => 'High', 'labels' => 'frontend, auth', 'assignee' => 'ada@acme.com', 'parent' => 'APP-100', 'due_date' => '2026-03-10T08:00:00Z', 'components' => 'Web',
        ]), []);

        $body = jiraRequest('POST', '/issue')['body']['fields'];

        expect($body)->toMatchArray([
            'project' => ['key' => 'APP'], 'issuetype' => ['name' => 'Bug'], 'summary' => 'Login fails', 'priority' => ['name' => 'High'],
            'labels' => ['frontend', 'auth'], 'assignee' => ['accountId' => JIRA_ACCOUNT], 'parent' => ['key' => 'APP-100'], 'duedate' => '2026-03-10', 'components' => [['name' => 'Web']],
        ])
            ->and($body['description']['type'])->toBe('doc')
            ->and($body['description']['content'][0])->toMatchArray(['type' => 'heading', 'attrs' => ['level' => 2]])
            ->and($body['description']['content'][1]['type'])->toBe('bulletList')
            ->and(jiraRequest('GET', '/user/search')['query'])->toMatchArray(['query' => 'ada@acme.com'])
            ->and($output)->toMatchArray([
                'key' => 'APP-1', 'url' => 'https://acme.atlassian.net/browse/APP-1', 'summary' => 'Login fails', 'description' => 'Steps to reproduce',
                'status' => 'In Progress', 'priority' => 'High', 'issue_type' => 'Bug', 'project' => 'APP', 'assignee' => 'Ada', 'assignee_id' => JIRA_ACCOUNT,
                'labels' => ['frontend', 'auth'], 'components' => ['Web'], 'fix_versions' => ['1.2'], 'parent_key' => 'APP-100', 'due_date' => '2026-03-10',
            ]);
    });

    it('turns custom field text into what each field wants, from the project\'s own field list', function () {
        fakeJira([
            'GET /issue/createmeta/APP/issuetypes/10002' => ['fields' => [
                ['fieldId' => 'customfield_10016', 'name' => 'Story Points', 'schema' => ['type' => 'number']],
                ['fieldId' => 'customfield_10020', 'name' => 'Component Group', 'schema' => ['type' => 'option']],
                ['fieldId' => 'customfield_10021', 'name' => 'Affected Systems', 'schema' => ['type' => 'array', 'items' => 'option']],
                ['fieldId' => 'customfield_10022', 'name' => 'Location', 'schema' => ['type' => 'option-with-child']],
                ['fieldId' => 'customfield_10023', 'name' => 'Tags', 'schema' => ['type' => 'array', 'items' => 'string']],
                ['fieldId' => 'customfield_10024', 'name' => 'Notes', 'schema' => ['type' => 'string']],
            ]],
            'GET /issue/createmeta/APP/issuetypes' => ['issueTypes' => [['id' => '10002', 'name' => 'Story']]],
            'POST /issue' => ['id' => '10001', 'key' => 'APP-2'],
            'GET /issue/APP-2' => jiraIssue('APP-2'),
        ]);

        (new JiraCreateIssueNode)->execute(Run::factory()->create(), jiraConfig([
            'project' => 'APP', 'issue_type' => 'story', 'summary' => 'x',
            'custom_fields' => ['Story Points' => '5', 'customfield_10020' => 'Frontend', 'affected systems' => 'Website, Mobile App', 'Location' => 'North America > United States', 'Tags' => 'a,b', 'Notes' => 'hello'],
            'extra_fields' => ['environment' => 'prod'],
        ]), []);

        expect(jiraRequest('POST', '/issue')['body']['fields'])->toMatchArray([
            'customfield_10016' => 5,
            'customfield_10020' => ['value' => 'Frontend'],
            'customfield_10021' => [['value' => 'Website'], ['value' => 'Mobile App']],
            'customfield_10022' => ['value' => 'North America', 'child' => ['value' => 'United States']],
            'customfield_10023' => ['a', 'b'],
            'customfield_10024' => 'hello',
            'environment' => 'prod',
        ]);
    });

    it('refuses a custom field the issue does not have, and a non-number for a number', function () {
        fakeJira([
            'GET /issue/createmeta/APP/issuetypes/10002' => ['fields' => [['fieldId' => 'customfield_10016', 'name' => 'Story Points', 'schema' => ['type' => 'number']]]],
        ]);
        $run = Run::factory()->create();
        $base = ['project' => 'APP', 'issue_type' => '10002', 'summary' => 'x'];

        expect(fn () => (new JiraCreateIssueNode)->execute($run, jiraConfig([...$base, 'custom_fields' => ['Nope' => '1']]), []))->toThrow(InvalidArgumentException::class, 'no field called "Nope"')
            ->and(fn () => (new JiraCreateIssueNode)->execute($run, jiraConfig([...$base, 'custom_fields' => ['Story Points' => 'many']]), []))->toThrow(InvalidArgumentException::class, 'not a number');
    });

    it('asks for a person by name only when it is unambiguous', function () {
        fakeJira(['GET /user/search' => [
            ['accountId' => 'a1', 'displayName' => 'Ada Lovelace', 'accountType' => 'atlassian', 'active' => true],
            ['accountId' => 'a2', 'displayName' => 'Ada Byron', 'accountType' => 'atlassian', 'active' => true],
            ['accountId' => 'a3', 'displayName' => 'Ada Bot', 'accountType' => 'app', 'active' => true],
        ]]);

        (new JiraCreateIssueNode)->execute(Run::factory()->create(), jiraConfig(['project' => 'APP', 'issue_type' => '1', 'summary' => 'x', 'assignee' => 'Ada']), []);
    })->throws(InvalidArgumentException::class, 'More than one Jira user matches "Ada"');

    it('gets an issue with its comments, attachments and other fields', function () {
        $issue = jiraIssue('APP-1', ['attachment' => [['id' => '77', 'filename' => 'log.txt', 'size' => 12, 'mimeType' => 'text/plain']], 'customfield_10016' => 5, 'customfield_10020' => ['value' => 'Frontend'], 'customfield_10021' => [['name' => 'x'], ['displayName' => 'Ada']]]);
        fakeJira([
            'GET /issue/APP-1/comment' => ['comments' => [['id' => '5', 'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Seen']]]]], 'author' => ['displayName' => 'Bob', 'accountId' => 'bob-id'], 'created' => '2026-03-01T12:00:00.000+0000']], 'isLast' => true],
            'GET /issue/APP-1' => $issue,
        ]);

        $output = (new JiraGetIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'include_comments' => true, 'extra_fields' => 'customfield_10016, customfield_10020, customfield_10021']), []);

        expect($output['attachments'])->toBe([['id' => '77', 'filename' => 'log.txt', 'size' => 12, 'mime_type' => 'text/plain', 'author' => null, 'created_at' => null, 'content_url' => null]])
            ->and($output['custom_fields'])->toBe(['customfield_10016' => 5, 'customfield_10020' => 'Frontend', 'customfield_10021' => ['x', 'Ada']])
            ->and($output['comments'][0])->toMatchArray(['id' => '5', 'body' => 'Seen', 'author' => 'Bob', 'internal' => false])
            ->and(jiraRequest('GET', '/issue/APP-1')['query']['fields'])->toContain('summary')->toContain('attachment')->toContain('customfield_10016');
    });

    it('does not let an issue key walk the request path', function () {
        (new JiraGetIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1/../../x']), []);
    })->throws(InvalidArgumentException::class, 'isn\'t an issue key');
});

describe('changing issues', function () {
    it('updates one issue: labels added and removed, a comment, an unassign, and no notification', function () {
        fakeJira(['PUT /issue/APP-1' => Http::response(null, 204)]);

        $output = (new JiraUpdateIssueNode)->execute(Run::factory()->create(), jiraConfig([
            'issue' => 'APP-1', 'summary' => 'New title', 'add_labels' => 'urgent', 'remove_labels' => 'triage', 'assignee' => 'unassigned', 'comment' => 'On it', 'notify' => false,
        ]), []);

        $sent = jiraRequest('PUT', '/issue/APP-1');

        expect($sent['query'])->toMatchArray(['notifyUsers' => 'false'])
            ->and($sent['body']['fields'])->toMatchArray(['summary' => 'New title', 'assignee' => ['accountId' => null]])
            ->and($sent['body']['update']['labels'])->toBe([['add' => 'urgent'], ['remove' => 'triage']])
            ->and($sent['body']['update']['comment'][0]['add']['body']['type'])->toBe('doc')
            ->and($output)->toBe(['updated' => ['APP-1'], 'urls' => ['https://acme.atlassian.net/browse/APP-1'], 'count' => 1]);
    });

    it('sets the labels outright, or adds and removes, not both', function () {
        (new JiraUpdateIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'labels' => 'a', 'add_labels' => 'b']), []);
    })->throws(InvalidArgumentException::class, 'not both');

    it('refuses an update that changes nothing', function () {
        (new JiraUpdateIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1']), []);
    })->throws(InvalidArgumentException::class, 'at least one field');

    it('updates the issues a JQL query finds, never more than the guard allows', function () {
        fakeJira([
            'POST /search/jql' => ['issues' => [jiraIssue('APP-1'), jiraIssue('APP-2'), jiraIssue('APP-3')], 'isLast' => true],
            'PUT /issue/' => Http::response(null, 204),
        ]);

        $output = (new JiraUpdateIssueNode)->execute(Run::factory()->create(), jiraConfig(['filter_mode' => 'jql', 'jql' => 'project = APP AND priority = High ORDER BY created', 'max_issues' => 2, 'priority' => 'Highest']), []);

        $search = jiraRequest('POST', '/search/jql')['body'];

        expect($search['jql'])->toBe('project = APP AND priority = High')
            ->and($search['maxResults'])->toBe(2)
            ->and($output['updated'])->toBe(['APP-1', 'APP-2'])
            ->and(collect(jiraSent())->where('method', 'PUT')->pluck('body.fields.priority.name')->values()->all())->toBe(['Highest', 'Highest']);
    });

    it('updates custom fields using the issue\'s edit metadata', function () {
        fakeJira([
            'GET /issue/APP-1/editmeta' => ['fields' => ['customfield_10016' => ['name' => 'Story Points', 'schema' => ['type' => 'number']]]],
            'PUT /issue/APP-1' => Http::response(null, 204),
        ]);

        (new JiraUpdateIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'custom_fields' => ['Story Points' => '8']]), []);

        expect(jiraRequest('PUT', '/issue/APP-1')['body']['fields'])->toBe(['customfield_10016' => 8]);
    });

    it('deletes an issue, and its sub-tasks when asked', function () {
        fakeJira(['DELETE /issue/APP-1' => Http::response(null, 204)]);

        $output = (new JiraDeleteIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'delete_subtasks' => true]), []);

        expect($output)->toBe(['deleted' => true, 'key' => 'APP-1'])
            ->and(jiraRequest('DELETE', '/issue/APP-1')['query'])->toMatchArray(['deleteSubtasks' => 'true']);
    });

    it('moves an issue by transition name, by the status it leads to, or by id', function (string $wanted) {
        fakeJira([
            'GET /issue/APP-1/transitions' => ['transitions' => [
                ['id' => '11', 'name' => 'Start work', 'to' => ['name' => 'In Progress', 'statusCategory' => ['name' => 'In Progress']]],
                ['id' => '31', 'name' => 'Finish', 'to' => ['name' => 'Done', 'statusCategory' => ['name' => 'Done']]],
            ]],
            'POST /issue/APP-1/transitions' => Http::response(null, 204),
        ]);

        $output = (new JiraTransitionIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'transition' => $wanted, 'comment' => 'Shipped', 'resolution' => 'Done']), []);

        expect($output)->toMatchArray(['key' => 'APP-1', 'transition_id' => '31', 'transition' => 'Finish', 'status' => 'Done'])
            ->and(jiraRequest('POST', '/issue/APP-1/transitions')['body'])->toMatchArray(['transition' => ['id' => '31'], 'fields' => ['resolution' => ['name' => 'Done']]])
            ->and(jiraRequest('POST', '/issue/APP-1/transitions')['body']['update']['comment'][0]['add']['body']['type'])->toBe('doc');
    })->with(['by transition name' => ['finish'], 'by status' => ['DONE'], 'by id' => ['31']]);

    it('says which moves are open when the one asked for is not', function () {
        fakeJira(['GET /issue/APP-1/transitions' => ['transitions' => [['id' => '11', 'name' => 'Start work', 'to' => ['name' => 'In Progress']]]]]);

        (new JiraTransitionIssueNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'transition' => 'Done']), []);
    })->throws(InvalidArgumentException::class, 'It can: Start work.');

    it('lists the open transitions', function () {
        fakeJira(['GET /issue/APP-1/transitions' => ['transitions' => [['id' => '11', 'name' => 'Start work', 'hasScreen' => false, 'to' => ['name' => 'In Progress', 'statusCategory' => ['name' => 'In Progress']]]]]]);

        $output = (new JiraListTransitionsNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1']), []);

        expect($output['transitions'][0])->toBe(['id' => '11', 'name' => 'Start work', 'to_status' => 'In Progress', 'to_status_category' => 'In Progress', 'has_screen' => false]);
    });
});

describe('finding issues', function () {
    it('searches with JQL and follows the page token', function () {
        $calls = 0;
        fakeJira(['POST /search/jql' => function () use (&$calls): array {
            $calls++;

            return $calls === 1
                ? ['issues' => [jiraIssue('APP-1')], 'nextPageToken' => 'tok-2', 'isLast' => false]
                : ['issues' => [jiraIssue('APP-2')], 'isLast' => true];
        }]);

        $output = (new JiraSearchIssuesNode)->execute(Run::factory()->create(), jiraConfig(['jql' => 'project = APP ORDER BY created DESC', 'limit' => 10, 'fields' => 'summary,status']), []);

        $bodies = collect(jiraSent())->pluck('body');

        expect(array_column($output['issues'], 'key'))->toBe(['APP-1', 'APP-2'])
            ->and($output['issues'][0]['url'])->toBe('https://acme.atlassian.net/browse/APP-1')
            ->and($bodies[0])->toBe(['jql' => 'project = APP ORDER BY created DESC', 'maxResults' => 10, 'fields' => ['summary', 'status']])
            ->and($bodies[1]['nextPageToken'])->toBe('tok-2');
    });

    it('builds JQL from the fields, quoting every value', function () {
        fakeJira([
            'GET /user/search' => [['accountId' => JIRA_ACCOUNT, 'displayName' => 'Ada', 'emailAddress' => 'ada@acme.com', 'accountType' => 'atlassian', 'active' => true]],
            'POST /search/jql' => ['issues' => [], 'isLast' => true],
        ]);

        (new JiraListIssuesNode)->execute(Run::factory()->create(), jiraConfig([
            'project' => 'APP', 'statuses' => 'In Progress, "Done"', 'priorities' => 'High', 'issue_types' => 'Bug', 'labels' => 'backend', 'assignee' => 'ada@acme.com',
            'custom_filters' => ['Story Points' => 5, 'customfield_10020' => 'Frontend'], 'custom_filters_match' => 'OR', 'order_by' => 'created DESC',
        ]), []);

        expect(jiraRequest('POST', '/search/jql')['body']['jql'])->toBe(
            'project = "APP" AND status in ("In Progress", "\"Done\"") AND priority in ("High") AND issuetype in ("Bug") AND labels in ("backend")'
            .' AND assignee = "'.JIRA_ACCOUNT.'" AND ("Story Points" = 5 OR cf[10020] = "Frontend") ORDER BY created DESC'
        );
    });

    it('uses a JQL query as it is, minus its ordering, or the JQL of a saved filter', function () {
        fakeJira([
            'GET /filter/10042' => ['id' => '10042', 'name' => 'Mine', 'jql' => 'assignee = currentUser() ORDER BY rank'],
            'POST /search/jql' => ['issues' => [], 'isLast' => true],
        ]);
        $run = Run::factory()->create();

        (new JiraListIssuesNode)->execute($run, jiraConfig(['filter_mode' => 'jql', 'jql' => 'project = APP ORDER BY created']), []);
        (new JiraListIssuesNode)->execute($run, jiraConfig(['filter_mode' => 'saved', 'saved_filter' => '10042']), []);

        $queries = collect(jiraSent())->where('path', '/search/jql')->pluck('body.jql')->values()->all();

        expect($queries)->toBe(['project = APP ORDER BY updated DESC', 'assignee = currentUser() ORDER BY updated DESC']);
    });

    it('will not search every issue at once', function () {
        fakeJira([]);

        (new JiraListIssuesNode)->execute(Run::factory()->create(), jiraConfig(), []);
    })->throws(InvalidArgumentException::class, 'does not search every issue');

    it('lists my issues, my recent activity and my permissions', function () {
        fakeJira([
            'POST /search/jql' => ['issues' => [jiraIssue()], 'isLast' => true],
            'GET /mypermissions' => ['permissions' => ['CREATE_ISSUES' => ['havePermission' => true], 'DELETE_ISSUES' => ['havePermission' => false]]],
        ]);
        $run = Run::factory()->create();

        (new JiraGetMyIssuesNode)->execute($run, jiraConfig(['project' => 'APP']), []);
        (new JiraGetMyRecentActivityNode)->execute($run, jiraConfig(['days' => 3]), []);
        $permissions = (new JiraGetMyPermissionsNode)->execute($run, jiraConfig(['project' => 'APP', 'permissions' => 'CREATE_ISSUES, DELETE_ISSUES']), []);

        $queries = collect(jiraSent())->where('path', '/search/jql')->pluck('body.jql')->values()->all();

        expect($queries[0])->toBe('assignee = currentUser() AND resolution = Unresolved AND project = "APP" ORDER BY updated DESC')
            ->and($queries[1])->toStartWith('updated >= -3d AND (assignee = currentUser() OR reporter = currentUser() OR watcher = currentUser() OR issuekey in issueHistory())')
            ->and($permissions)->toBe(['permissions' => ['CREATE_ISSUES' => true, 'DELETE_ISSUES' => false], 'granted' => ['CREATE_ISSUES']])
            ->and(jiraRequest('GET', '/mypermissions')['query'])->toMatchArray(['projectKey' => 'APP', 'permissions' => 'CREATE_ISSUES,DELETE_ISSUES']);
    });
});

describe('comments, attachments and metadata', function () {
    it('adds, lists, edits and deletes comments, with an internal flag for service desks', function () {
        $comment = ['id' => '5', 'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]], 'author' => ['displayName' => 'Ada', 'accountId' => JIRA_ACCOUNT], 'jsdPublic' => false, 'created' => 'c', 'updated' => 'u'];
        fakeJira([
            'POST /issue/APP-1/comment' => $comment,
            'GET /issue/APP-1/comment' => ['comments' => [$comment], 'isLast' => true],
            'PUT /issue/APP-1/comment/5' => $comment,
            'DELETE /issue/APP-1/comment/5' => Http::response(null, 204),
        ]);
        $run = Run::factory()->create();

        $added = (new JiraAddCommentNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'body' => 'Hi **there**', 'internal' => true]), []);
        $listed = (new JiraListCommentsNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'newest_first' => true]), []);
        $edited = (new JiraUpdateCommentNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'comment_id' => '5', 'body' => 'Edited']), []);
        $deleted = (new JiraDeleteCommentNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'comment_id' => '5']), []);

        $body = jiraRequest('POST', '/issue/APP-1/comment')['body'];

        expect($body['body']['content'][0]['content'][1])->toMatchArray(['text' => 'there', 'marks' => [['type' => 'strong']]])
            ->and($body['properties'])->toBe([['key' => 'sd.public.comment', 'value' => ['internal' => true]]])
            ->and($added)->toMatchArray(['id' => '5', 'body' => 'Hi', 'author' => 'Ada', 'internal' => true])
            ->and($listed['count'])->toBe(1)
            ->and(jiraRequest('GET', '/issue/APP-1/comment')['query'])->toMatchArray(['orderBy' => '-created'])
            ->and($edited['id'])->toBe('5')
            ->and($deleted)->toBe(['deleted' => true, 'id' => '5']);
    });

    it('attaches a file from text or base64, with the header Jira requires', function () {
        fakeJira(['POST /issue/APP-1/attachments' => [['id' => '77', 'filename' => 'log.txt', 'size' => 5, 'mimeType' => 'text/plain', 'author' => ['displayName' => 'Ada'], 'created' => 'c', 'content' => 'https://acme.atlassian.net/c/77']]]);
        $run = Run::factory()->create();

        $viaText = (new JiraAddAttachmentNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'filename' => 'log.txt', 'content' => 'hello']), []);
        (new JiraAddAttachmentNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'filename' => 'log.txt', 'content_base64' => base64_encode('hello')]), []);

        expect($viaText['attachments'][0])->toMatchArray(['id' => '77', 'filename' => 'log.txt', 'author' => 'Ada', 'content_url' => 'https://acme.atlassian.net/c/77']);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/issue/APP-1/attachments')
            && $request->hasHeader('X-Atlassian-Token', 'no-check')
            && str_contains($request->body(), 'name="file"; filename="log.txt"')
            && str_contains($request->body(), 'hello'));
    });

    it('refuses an attachment that is empty, not base64, or too large', function (array $config) {
        fakeJira([]);

        (new JiraAddAttachmentNode)->execute(Run::factory()->create(), jiraConfig(['issue' => 'APP-1', 'filename' => 'f.txt', ...$config]), []);
    })->throws(InvalidArgumentException::class)->with([
        'empty' => [[]],
        'not base64' => [['content_base64' => '!!!']],
        'too large' => [['content' => str_repeat('x', 10_485_761)]],
    ]);

    it('downloads an attachment as base64, and as text when it is text', function () {
        fakeJira([
            'GET /attachment/content/77' => Http::response('hello', 200, ['Content-Type' => 'text/plain']),
            'GET /attachment/77' => ['id' => '77', 'filename' => 'log.txt', 'size' => 5, 'mimeType' => 'text/plain'],
        ]);

        $output = (new JiraDownloadAttachmentNode)->execute(Run::factory()->create(), jiraConfig(['attachment_id' => '77']), []);

        expect($output)->toMatchArray(['filename' => 'log.txt', 'content_base64' => base64_encode('hello'), 'content' => 'hello']);
    });

    it('will not download a file over 10 MB', function () {
        fakeJira(['GET /attachment/77' => ['id' => '77', 'filename' => 'big.bin', 'size' => 20_000_000, 'mimeType' => 'application/octet-stream']]);

        (new JiraDownloadAttachmentNode)->execute(Run::factory()->create(), jiraConfig(['attachment_id' => '77']), []);
    })->throws(RuntimeException::class, 'larger than 10 MB');

    it('lists fields, edit metadata, issue types, priorities and saved filters', function () {
        fakeJira([
            'GET /field' => [['id' => 'summary', 'name' => 'Summary', 'custom' => false, 'schema' => ['type' => 'string']], ['id' => 'customfield_10016', 'name' => 'Story Points', 'custom' => true, 'schema' => ['type' => 'number'], 'searchable' => true]],
            'GET /issue/APP-1/editmeta' => ['fields' => ['priority' => ['name' => 'Priority', 'required' => false, 'schema' => ['type' => 'priority'], 'operations' => ['set'], 'allowedValues' => [['name' => 'High'], ['name' => 'Low']]]]],
            'GET /issue/createmeta/APP/issuetypes' => ['issueTypes' => [['id' => '1', 'name' => 'Bug', 'subtask' => false, 'hierarchyLevel' => 0]]],
            'GET /issuetype' => [['id' => '2', 'name' => 'Task', 'subtask' => false]],
            'GET /priority/search' => ['values' => [['id' => '3', 'name' => 'Medium', 'description' => 'Normal']], 'isLast' => true],
            'GET /filter/search' => ['values' => [['id' => '10042', 'name' => 'Mine', 'jql' => 'assignee = currentUser()', 'owner' => ['displayName' => 'Ada']]], 'isLast' => true],
        ]);
        $run = Run::factory()->create();

        $fields = (new JiraListFieldsNode)->execute($run, jiraConfig(['custom_only' => true, 'query' => 'story']), []);
        $meta = (new JiraGetEditMetadataNode)->execute($run, jiraConfig(['issue' => 'APP-1']), []);
        $types = (new JiraListIssueTypesNode)->execute($run, jiraConfig(['project' => 'APP']), []);
        $allTypes = (new JiraListIssueTypesNode)->execute($run, jiraConfig(), []);
        $priorities = (new JiraListPrioritiesNode)->execute($run, jiraConfig(), []);
        $filters = (new JiraListFiltersNode)->execute($run, jiraConfig(['query' => 'mine']), []);

        expect(array_column($fields['fields'], 'id'))->toBe(['customfield_10016'])
            ->and($meta['fields'][0])->toBe(['id' => 'priority', 'name' => 'Priority', 'required' => false, 'type' => 'priority', 'operations' => ['set'], 'allowed_values' => ['High', 'Low']])
            ->and($types['issue_types'][0])->toMatchArray(['id' => '1', 'name' => 'Bug'])
            ->and($allTypes['issue_types'][0]['name'])->toBe('Task')
            ->and($priorities['priorities'][0])->toBe(['id' => '3', 'name' => 'Medium', 'description' => 'Normal'])
            ->and($filters['filters'][0])->toMatchArray(['id' => '10042', 'jql' => 'assignee = currentUser()', 'owner' => 'Ada'])
            ->and(jiraRequest('GET', '/filter/search')['query'])->toMatchArray(['filterName' => 'mine', 'expand' => 'jql']);
    });
});

describe('people, groups and links', function () {
    it('lists and finds people, leaving out apps and people who left', function () {
        $people = [
            ['accountId' => 'a1', 'displayName' => 'Ada', 'emailAddress' => 'ada@acme.com', 'accountType' => 'atlassian', 'active' => true, 'timeZone' => 'UTC'],
            ['accountId' => 'a2', 'displayName' => 'Bot', 'accountType' => 'app', 'active' => true],
            ['accountId' => 'a3', 'displayName' => 'Gone', 'accountType' => 'atlassian', 'active' => false],
        ];
        fakeJira(['GET /users/search' => $people, 'GET /user/search' => $people]);
        $run = Run::factory()->create();

        $everyone = (new JiraListUsersNode)->execute($run, jiraConfig(), []);
        $found = (new JiraListUsersNode)->execute($run, jiraConfig(['query' => 'a', 'include_inactive' => true]), []);

        expect(array_column($everyone['users'], 'account_id'))->toBe(['a1'])
            ->and($everyone['users'][0])->toMatchArray(['display_name' => 'Ada', 'email' => 'ada@acme.com', 'time_zone' => 'UTC'])
            ->and(array_column($found['users'], 'account_id'))->toBe(['a1', 'a3']);
    });

    it('makes someone the assignee, reporter or a watcher', function () {
        fakeJira([
            'PUT /issue/APP-1/assignee' => Http::response(null, 204),
            'PUT /issue/APP-1' => Http::response(null, 204),
            'POST /issue/APP-1/watchers' => Http::response(null, 204),
        ]);
        $run = Run::factory()->create();

        foreach (['assignee', 'reporter', 'watcher'] as $role) {
            expect((new JiraAddUserToIssueNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'role' => $role, 'user' => JIRA_ACCOUNT]), []))->toBe(['key' => 'APP-1', 'role' => $role, 'account_id' => JIRA_ACCOUNT]);
        }

        expect(jiraRequest('PUT', '/issue/APP-1/assignee')['body'])->toBe(['accountId' => JIRA_ACCOUNT])
            ->and(jiraRequest('PUT', '/issue/APP-1')['body'])->toBe(['fields' => ['reporter' => ['accountId' => JIRA_ACCOUNT]]])
            ->and(jiraRequest('POST', '/issue/APP-1/watchers')['raw'])->toBe('"'.JIRA_ACCOUNT.'"');
    });

    it('gets the current user', function () {
        fakeJira(['GET /myself' => ['accountId' => JIRA_ACCOUNT, 'displayName' => 'Ada', 'emailAddress' => 'ada@acme.com', 'active' => true, 'accountType' => 'atlassian']]);

        expect((new JiraGetMyselfNode)->execute(Run::factory()->create(), jiraConfig(), []))->toMatchArray(['account_id' => JIRA_ACCOUNT, 'display_name' => 'Ada']);
    });

    it('lists, creates and fills groups', function () {
        fakeJira([
            'GET /group/bulk' => ['values' => [['groupId' => 'g1', 'name' => 'jira-developers']], 'isLast' => true],
            'POST /group/user' => ['name' => 'jira-developers'],
            'DELETE /group/user' => Http::response(null, 204),
            'POST /group' => ['groupId' => 'g2', 'name' => 'qa'],
        ]);
        $run = Run::factory()->create();

        $listed = (new JiraListGroupsNode)->execute($run, jiraConfig(['query' => 'jira']), []);
        $created = (new JiraCreateGroupNode)->execute($run, jiraConfig(['name' => 'qa']), []);
        (new JiraAddUserToGroupNode)->execute($run, jiraConfig(['group' => 'jira-developers', 'user' => JIRA_ACCOUNT]), []);
        (new JiraRemoveUserFromGroupNode)->execute($run, jiraConfig(['group' => 'jira-developers', 'user' => JIRA_ACCOUNT]), []);

        expect($listed['groups'])->toBe([['id' => 'g1', 'name' => 'jira-developers']])
            ->and($created)->toBe(['id' => 'g2', 'name' => 'qa'])
            ->and(jiraRequest('POST', '/group/user'))->toMatchArray(['query' => ['groupname' => 'jira-developers'], 'body' => ['accountId' => JIRA_ACCOUNT]])
            ->and(jiraRequest('DELETE', '/group/user')['query'])->toBe(['groupname' => 'jira-developers', 'accountId' => JIRA_ACCOUNT]);
    });

    it('lists link types, links two issues either way round, reads and deletes links', function () {
        fakeJira([
            'GET /issueLinkType' => ['issueLinkTypes' => [['id' => '1', 'name' => 'Blocks', 'inward' => 'is blocked by', 'outward' => 'blocks']]],
            'POST /issueLink' => Http::response(null, 201),
            'DELETE /issueLink/900' => Http::response(null, 204),
            'GET /issue/APP-1' => ['fields' => ['issuelinks' => [
                ['id' => '900', 'type' => ['name' => 'Blocks', 'inward' => 'is blocked by', 'outward' => 'blocks'], 'outwardIssue' => ['key' => 'APP-2', 'fields' => ['summary' => 'Two', 'status' => ['name' => 'Done']]]],
                ['id' => '901', 'type' => ['name' => 'Relates', 'inward' => 'relates to', 'outward' => 'relates to'], 'inwardIssue' => ['key' => 'APP-3', 'fields' => ['summary' => 'Three', 'status' => ['name' => 'To Do']]]],
            ]]],
        ]);
        $run = Run::factory()->create();

        $types = (new JiraListIssueLinkTypesNode)->execute($run, jiraConfig(), []);
        (new JiraCreateIssueLinkNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'link_type' => 'Blocks', 'other_issue' => 'APP-2', 'comment' => 'See this']), []);
        (new JiraCreateIssueLinkNode)->execute($run, jiraConfig(['issue' => 'APP-1', 'link_type' => 'Blocks', 'direction' => 'inward', 'other_issue' => 'APP-2']), []);
        $links = (new JiraGetIssueLinksNode)->execute($run, jiraConfig(['issue' => 'APP-1']), []);
        $deleted = (new JiraDeleteIssueLinkNode)->execute($run, jiraConfig(['link_id' => '900']), []);

        $created = collect(jiraSent())->where('path', '/issueLink')->pluck('body')->values()->all();

        expect($types['link_types'][0])->toBe(['id' => '1', 'name' => 'Blocks', 'inward' => 'is blocked by', 'outward' => 'blocks'])
            ->and($created[0])->toMatchArray(['type' => ['name' => 'Blocks'], 'inwardIssue' => ['key' => 'APP-2'], 'outwardIssue' => ['key' => 'APP-1']])
            ->and($created[0]['comment']['body']['type'])->toBe('doc')
            ->and($created[1])->toBe(['type' => ['name' => 'Blocks'], 'inwardIssue' => ['key' => 'APP-1'], 'outwardIssue' => ['key' => 'APP-2']])
            ->and($links['links'][0])->toBe(['id' => '900', 'type' => 'Blocks', 'relationship' => 'blocks', 'direction' => 'outward', 'issue_key' => 'APP-2', 'issue_summary' => 'Two', 'issue_status' => 'Done'])
            ->and($links['links'][1])->toMatchArray(['relationship' => 'relates to', 'direction' => 'inward', 'issue_key' => 'APP-3'])
            ->and($deleted)->toBe(['deleted' => true, 'id' => '900']);
    });
});

describe('service management', function () {
    it('lists desks, request types and their fields, and raises and reads requests', function () {
        $request = ['issueKey' => 'HELP-4', 'issueId' => '10040', 'requestTypeId' => '10', 'serviceDeskId' => '1', 'currentStatus' => ['status' => 'Waiting for support'], 'reporter' => ['displayName' => 'Cy'], 'createdDate' => ['iso8601' => '2026-03-01T10:00:00+0000'], '_links' => ['web' => 'https://acme.atlassian.net/servicedesk/customer/portal/1/HELP-4'], 'requestFieldValues' => [['fieldId' => 'summary', 'value' => 'Printer broken']]];
        fakeJira([
            'GET /servicedesk/1/requesttype/10/field' => ['requestTypeFields' => [['fieldId' => 'summary', 'name' => 'Summary', 'required' => true, 'jiraSchema' => ['type' => 'string'], 'validValues' => []], ['fieldId' => 'priority', 'name' => 'Priority', 'required' => false, 'jiraSchema' => ['type' => 'priority'], 'validValues' => [['label' => 'High', 'value' => '2']]]]],
            'GET /servicedesk/1/requesttype' => ['values' => [['id' => '10', 'name' => 'Report a problem', 'description' => 'd', 'helpText' => 'h', 'groupIds' => ['1']]], 'isLastPage' => true],
            'GET /servicedesk' => ['values' => [['id' => '1', 'projectId' => '10050', 'projectKey' => 'HELP', 'projectName' => 'Help']], 'isLastPage' => true],
            'POST /request' => $request,
            'GET /request/HELP-4' => $request,
            'GET /request' => ['values' => [$request], 'isLastPage' => true],
        ]);
        $run = Run::factory()->create();

        $desks = (new JiraListServiceDesksNode)->execute($run, jiraConfig(), []);
        $types = (new JiraGetRequestTypesNode)->execute($run, jiraConfig(['service_desk' => '1']), []);
        $fields = (new JiraGetRequestTypeFieldsNode)->execute($run, jiraConfig(['service_desk' => '1', 'request_type' => '10']), []);
        $created = (new JiraCreateCustomerRequestNode)->execute($run, jiraConfig(['service_desk' => '1', 'request_type' => '10', 'summary' => 'Printer broken', 'description' => 'Jams', 'on_behalf_of' => 'cy@example.com', 'extra_fields' => ['customfield_10010' => 'Chrome']]), []);
        $got = (new JiraGetCustomerRequestNode)->execute($run, jiraConfig(['issue' => 'HELP-4']), []);
        $listed = (new JiraListCustomerRequestsNode)->execute($run, jiraConfig(['service_desk' => '1', 'status' => 'OPEN_REQUESTS', 'query' => 'printer']), []);

        expect($desks['service_desks'][0])->toBe(['id' => '1', 'project_id' => '10050', 'project_key' => 'HELP', 'name' => 'Help'])
            ->and($types['request_types'][0])->toMatchArray(['id' => '10', 'name' => 'Report a problem'])
            ->and($fields['fields'][0])->toMatchArray(['id' => 'summary', 'required' => true, 'type' => 'string'])
            ->and($fields['fields'][1]['valid_values'])->toBe(['High'])
            ->and($created)->toMatchArray(['key' => 'HELP-4', 'status' => 'Waiting for support', 'reporter' => 'Cy', 'url' => 'https://acme.atlassian.net/servicedesk/customer/portal/1/HELP-4'])
            ->and(jiraRequest('POST', '/request')['body'])->toBe([
                'serviceDeskId' => '1', 'requestTypeId' => '10', 'requestFieldValues' => ['summary' => 'Printer broken', 'description' => 'Jams', 'customfield_10010' => 'Chrome'], 'raiseOnBehalfOf' => 'cy@example.com',
            ])
            ->and($got['summary'])->toBe('Printer broken')
            ->and($listed['count'])->toBe(1)
            ->and(jiraRequest('GET', '/request')['query'])->toMatchArray(['serviceDeskId' => '1', 'requestStatus' => 'OPEN_REQUESTS', 'searchTerm' => 'printer']);
    });
});

describe('as triggers', function () {
    afterEach(fn () => Carbon::setTestNow());

    it('reads new and updated issues inside the poll window, newest first', function () {
        fakeJira(['POST /search/jql' => ['issues' => [], 'isLast' => true]]);
        $run = Run::factory()->create();

        $new = new JiraListNewIssuesNode;
        $updated = new JiraListUpdatedIssuesNode;

        $new->execute($run, jiraConfig($new->triggerReadConfig(['project' => 'APP'])), []);
        $updated->execute($run, jiraConfig(['filter_mode' => 'jql', 'jql' => 'project = APP OR project = OPS ORDER BY rank', 'window_minutes' => 30]), []);

        $queries = collect(jiraSent())->pluck('body.jql')->values()->all();

        expect($queries)->toBe([
            'project = "APP" AND created >= -360m ORDER BY created DESC',
            '(project = APP OR project = OPS) AND updated >= -30m ORDER BY updated DESC',
        ]);
    });

    it('is a trigger with its own names, a one minute poll and per-change item ids', function () {
        $new = new JiraListNewIssuesNode;
        $updated = new JiraListUpdatedIssuesNode;
        $issues = [
            ['key' => 'APP-1', 'created_at' => '2026-03-01T10:00:00.000+0000', 'updated_at' => '2026-03-01T10:00:10.000+0000'],
            ['key' => 'APP-2', 'created_at' => '2026-03-01T10:00:00.000+0000', 'updated_at' => '2026-03-01T11:00:00.000+0000'],
        ];

        expect($new)->toBeInstanceOf(SupportsTriggerMode::class)
            ->and($new->triggerName())->toBe('Jira: New issue')
            ->and($updated->triggerName())->toBe('Jira: Issue updated')
            ->and($new->triggerPollMinutes())->toBe(1)
            ->and($new->triggerListsNewestFirst())->toBeTrue()
            ->and(array_column($updated->triggerItems(['issues' => $issues], []), 'key'))->toBe(['APP-2'])
            ->and($updated->triggerItemId($issues[1]))->toBe('APP-2:2026-03-01T11:00:00.000+0000');
    });

    it('is polled like any app trigger: a baseline first, then one run for each new issue', function () {
        Queue::fake([FireTriggerEvent::class]);

        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'jira') ?? Connector::factory()->create(['key' => 'jira']))
            ->create(['data' => ['access_token' => 'atl_test'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'jira_list_new_issues', 'config' => ['credential_id' => $credential->id, 'activate' => true, 'project' => 'APP']],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();

        fakeJira(['POST /search/jql' => ['issues' => [jiraIssue('APP-1')], 'isLast' => true]]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        fakeJira(['POST /search/jql' => ['issues' => [jiraIssue('APP-3'), jiraIssue('APP-2'), jiraIssue('APP-1')], 'isLast' => true]]);
        dispatch_sync(new PollTrigger($trigger));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events->pluck('payload.key')->sort()->values()->all())->toBe(['APP-2', 'APP-3']);
    });
});
