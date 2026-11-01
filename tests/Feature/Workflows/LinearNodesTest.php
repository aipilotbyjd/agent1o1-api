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
use App\Nodes\Integrations\Linear\AbstractLinearNode;
use App\Nodes\Integrations\Linear\LinearCreateCommentNode;
use App\Nodes\Integrations\Linear\LinearCreateInitiativeNode;
use App\Nodes\Integrations\Linear\LinearCreateIssueNode;
use App\Nodes\Integrations\Linear\LinearCreateIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearCreateProjectNode;
use App\Nodes\Integrations\Linear\LinearCreateStatusUpdateNode;
use App\Nodes\Integrations\Linear\LinearDeleteCommentNode;
use App\Nodes\Integrations\Linear\LinearDeleteInitiativeNode;
use App\Nodes\Integrations\Linear\LinearDeleteIssueNode;
use App\Nodes\Integrations\Linear\LinearDeleteIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearDeleteProjectNode;
use App\Nodes\Integrations\Linear\LinearDeleteStatusUpdateNode;
use App\Nodes\Integrations\Linear\LinearGetInitiativeNode;
use App\Nodes\Integrations\Linear\LinearGetIssueNode;
use App\Nodes\Integrations\Linear\LinearGetMeNode;
use App\Nodes\Integrations\Linear\LinearGetProjectNode;
use App\Nodes\Integrations\Linear\LinearListCommentsNode;
use App\Nodes\Integrations\Linear\LinearListCyclesNode;
use App\Nodes\Integrations\Linear\LinearListInitiativesNode;
use App\Nodes\Integrations\Linear\LinearListIssueRelationsNode;
use App\Nodes\Integrations\Linear\LinearListIssuesNode;
use App\Nodes\Integrations\Linear\LinearListLabelsNode;
use App\Nodes\Integrations\Linear\LinearListNewCommentsNode;
use App\Nodes\Integrations\Linear\LinearListNewIssuesNode;
use App\Nodes\Integrations\Linear\LinearListProjectsNode;
use App\Nodes\Integrations\Linear\LinearListStatusUpdatesNode;
use App\Nodes\Integrations\Linear\LinearListTeamsNode;
use App\Nodes\Integrations\Linear\LinearListUpdatedIssuesNode;
use App\Nodes\Integrations\Linear\LinearListUsersNode;
use App\Nodes\Integrations\Linear\LinearListWorkflowStatesNode;
use App\Nodes\Integrations\Linear\LinearSearchIssuesNode;
use App\Nodes\Integrations\Linear\LinearUpdateCommentNode;
use App\Nodes\Integrations\Linear\LinearUpdateInitiativeNode;
use App\Nodes\Integrations\Linear\LinearUpdateIssueNode;
use App\Nodes\Integrations\Linear\LinearUpdateIssueRelationNode;
use App\Nodes\Integrations\Linear\LinearUpdateProjectNode;
use App\Nodes\Integrations\Linear\LinearUpdateStatusUpdateNode;
use App\Services\Workflows\NodeRegistry;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const LINEAR_TEAM = '11111111-1111-4111-8111-111111111111';
const LINEAR_STATE = '22222222-2222-4222-8222-222222222222';
const LINEAR_USER = '33333333-3333-4333-8333-333333333333';
const LINEAR_ISSUE = '44444444-4444-4444-8444-444444444444';

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function linearConfig(array $config = []): array
{
    return ['access_token' => 'lin_test', ...$config];
}

/**
 * Answer Linear's one GraphQL endpoint. Each key is text that appears in the
 * query it answers, tried in order; the value is the `data` to return (or a
 * closure taking the request). A query nobody answers is an error, so a node
 * that asks for something unexpected fails the test. Each call starts from a
 * clean client, so what an earlier call recorded or answered can't leak in.
 *
 * @param  array<string, mixed>  $handlers
 */
function fakeLinear(array $handlers): void
{
    Http::swap(new Factory);
    Http::fake(['api.linear.app/graphql' => function (Request $request) use ($handlers) {
        foreach ($handlers as $needle => $data) {
            if (str_contains((string) $request['query'], $needle)) {
                return Http::response(['data' => value($data, $request)]);
            }
        }

        return Http::response(['errors' => [['message' => 'Nothing fakes: '.substr((string) $request['query'], 0, 90)]]]);
    }]);
}

/**
 * What the node sent: each request's query and variables.
 *
 * @return list<array{query: string, variables: array<string, mixed>}>
 */
function linearSent(): array
{
    return array_map(fn (array $pair): array => ['query' => (string) $pair[0]['query'], 'variables' => (array) ($pair[0]['variables'] ?? [])], Http::recorded()->all());
}

/**
 * The variables of the one request whose query holds `$needle`.
 *
 * @return array<string, mixed>
 */
function linearVariables(string $needle): array
{
    $found = array_values(array_filter(linearSent(), fn (array $sent): bool => str_contains($sent['query'], $needle)));

    expect($found)->toHaveCount(1);

    return $found[0]['variables'];
}

/**
 * A Linear issue as the API returns it.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function linearIssue(string $identifier = 'ENG-1', array $extra = []): array
{
    return [
        'id' => LINEAR_ISSUE,
        'identifier' => $identifier,
        'number' => 1,
        'title' => 'Login fails',
        'description' => 'Steps to reproduce',
        'url' => "https://linear.app/acme/issue/{$identifier}",
        'priority' => 2,
        'priorityLabel' => 'High',
        'estimate' => 3,
        'dueDate' => '2026-03-10',
        'createdAt' => '2026-03-01T10:00:00.000Z',
        'updatedAt' => '2026-03-01T10:00:05.000Z',
        'state' => ['id' => LINEAR_STATE, 'name' => 'In Progress', 'type' => 'started'],
        'team' => ['id' => LINEAR_TEAM, 'key' => 'ENG', 'name' => 'Engineering'],
        'project' => ['id' => 'p1', 'name' => 'Website'],
        'assignee' => ['id' => LINEAR_USER, 'name' => 'Ada', 'email' => 'ada@acme.com'],
        'creator' => ['id' => 'u2', 'name' => 'Bob'],
        'labels' => ['nodes' => [['id' => 'l1', 'name' => 'Bug'], ['id' => 'l2', 'name' => 'Auth']]],
        'parent' => null,
        'cycle' => ['id' => 'c1', 'number' => 4, 'name' => null],
        ...$extra,
    ];
}

it('registers every linear node with its own type under the linear category', function () {
    $registry = app(NodeRegistry::class);

    $expected = [
        LinearListIssuesNode::class => ['linear_list_issues', ActionEffect::Read],
        LinearSearchIssuesNode::class => ['linear_search_issues', ActionEffect::Read],
        LinearGetIssueNode::class => ['linear_get_issue', ActionEffect::Read],
        LinearCreateIssueNode::class => ['linear_create_issue', ActionEffect::External],
        LinearUpdateIssueNode::class => ['linear_update_issue', ActionEffect::Write],
        LinearDeleteIssueNode::class => ['linear_delete_issue', ActionEffect::Destructive],
        LinearListNewIssuesNode::class => ['linear_list_new_issues', ActionEffect::Read],
        LinearListUpdatedIssuesNode::class => ['linear_list_updated_issues', ActionEffect::Read],
        LinearListIssueRelationsNode::class => ['linear_list_issue_relations', ActionEffect::Read],
        LinearCreateIssueRelationNode::class => ['linear_create_issue_relation', ActionEffect::Write],
        LinearUpdateIssueRelationNode::class => ['linear_update_issue_relation', ActionEffect::Write],
        LinearDeleteIssueRelationNode::class => ['linear_delete_issue_relation', ActionEffect::Destructive],
        LinearListCommentsNode::class => ['linear_list_comments', ActionEffect::Read],
        LinearCreateCommentNode::class => ['linear_create_comment', ActionEffect::External],
        LinearUpdateCommentNode::class => ['linear_update_comment', ActionEffect::Write],
        LinearDeleteCommentNode::class => ['linear_delete_comment', ActionEffect::Destructive],
        LinearListNewCommentsNode::class => ['linear_list_new_comments', ActionEffect::Read],
        LinearListProjectsNode::class => ['linear_list_projects', ActionEffect::Read],
        LinearGetProjectNode::class => ['linear_get_project', ActionEffect::Read],
        LinearCreateProjectNode::class => ['linear_create_project', ActionEffect::External],
        LinearUpdateProjectNode::class => ['linear_update_project', ActionEffect::Write],
        LinearDeleteProjectNode::class => ['linear_delete_project', ActionEffect::Destructive],
        LinearListInitiativesNode::class => ['linear_list_initiatives', ActionEffect::Read],
        LinearGetInitiativeNode::class => ['linear_get_initiative', ActionEffect::Read],
        LinearCreateInitiativeNode::class => ['linear_create_initiative', ActionEffect::External],
        LinearUpdateInitiativeNode::class => ['linear_update_initiative', ActionEffect::Write],
        LinearDeleteInitiativeNode::class => ['linear_delete_initiative', ActionEffect::Destructive],
        LinearListStatusUpdatesNode::class => ['linear_list_status_updates', ActionEffect::Read],
        LinearCreateStatusUpdateNode::class => ['linear_create_status_update', ActionEffect::External],
        LinearUpdateStatusUpdateNode::class => ['linear_update_status_update', ActionEffect::Write],
        LinearDeleteStatusUpdateNode::class => ['linear_delete_status_update', ActionEffect::Destructive],
        LinearListTeamsNode::class => ['linear_list_teams', ActionEffect::Read],
        LinearListWorkflowStatesNode::class => ['linear_list_workflow_states', ActionEffect::Read],
        LinearListLabelsNode::class => ['linear_list_labels', ActionEffect::Read],
        LinearListUsersNode::class => ['linear_list_users', ActionEffect::Read],
        LinearListCyclesNode::class => ['linear_list_cycles', ActionEffect::Read],
        LinearGetMeNode::class => ['linear_get_me', ActionEffect::Read],
    ];

    foreach ($expected as $class => [$type, $effect]) {
        $node = $registry->resolve($type);

        expect($node)->toBeInstanceOf($class)
            ->and($node)->toBeInstanceOf(AbstractLinearNode::class)
            ->and($node->type())->toBe($type)
            ->and($node->category())->toBe('linear')
            ->and($node->effect([]))->toBe($effect)
            ->and(array_key_first($node->configSchema()['properties']))->toBe('credential_id');
    }

    expect($expected)->toHaveCount(37);
});

describe('reading issues', function () {
    it('lists issues, turning names into ids for the filter', function () {
        fakeLinear([
            'teams(filter' => ['teams' => ['nodes' => [['id' => LINEAR_TEAM]]]],
            'workflowStates(filter' => ['workflowStates' => ['nodes' => [['id' => LINEAR_STATE]]]],
            'issueLabels(filter' => ['issueLabels' => ['nodes' => [['id' => 'l1', 'team' => ['id' => LINEAR_TEAM]]]]],
            'users(filter' => ['users' => ['nodes' => [['id' => LINEAR_USER]]]],
            'issues(filter' => ['issues' => ['nodes' => [linearIssue()], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]]],
        ]);

        $output = (new LinearListIssuesNode)->execute(Run::factory()->create(), linearConfig([
            'team' => 'Engineering', 'state' => 'In Progress', 'labels' => 'Bug', 'assignee' => 'ada@acme.com', 'priority' => 'high',
        ]), []);

        expect($output['count'])->toBe(1)
            ->and($output['issues'][0])->toMatchArray([
                'identifier' => 'ENG-1', 'title' => 'Login fails', 'priority' => 2, 'priority_label' => 'High',
                'state' => 'In Progress', 'state_id' => LINEAR_STATE, 'team_key' => 'ENG', 'project' => 'Website',
                'assignee' => 'Ada', 'assignee_email' => 'ada@acme.com', 'labels' => ['Bug', 'Auth'], 'label_ids' => ['l1', 'l2'],
                'cycle' => 4, 'due_date' => '2026-03-10',
            ]);

        expect(linearVariables('issues(filter')['filter'])->toBe([
            'team' => ['id' => ['eq' => LINEAR_TEAM]],
            'state' => ['id' => ['eq' => LINEAR_STATE]],
            'assignee' => ['id' => ['eq' => LINEAR_USER]],
            'priority' => ['eq' => 2],
            'labels' => ['some' => ['id' => ['in' => ['l1']]]],
        ]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer lin_test'));
    });

    it('uses an id as it is, without looking it up', function () {
        fakeLinear(['issues(filter' => ['issues' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]);

        (new LinearListIssuesNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM]), []);

        expect(linearSent())->toHaveCount(1)
            ->and(linearVariables('issues(filter')['filter'])->toBe(['team' => ['id' => ['eq' => LINEAR_TEAM]]]);
    });

    it('sends no filter when none is set, and pages until it has the limit', function () {
        $page = fn (string $id, bool $more): array => ['issues' => ['nodes' => [linearIssue($id)], 'pageInfo' => ['hasNextPage' => $more, 'endCursor' => 'cur-'.$id]]];
        $calls = 0;
        fakeLinear(['issues(filter' => function (Request $request) use (&$calls, $page): array {
            $calls++;

            return $calls === 1 ? $page('ENG-1', true) : $page('ENG-2', false);
        }]);

        $output = (new LinearListIssuesNode)->execute(Run::factory()->create(), linearConfig(['limit' => 5]), []);

        expect(array_column($output['issues'], 'identifier'))->toBe(['ENG-1', 'ENG-2']);

        $sent = linearSent();

        expect($sent[0]['variables'])->not->toHaveKey('filter')
            ->and($sent[0]['variables']['first'])->toBe(5)
            ->and($sent[1]['variables']['after'])->toBe('cur-ENG-1')
            ->and($sent[1]['variables']['first'])->toBe(4);
    });

    it('searches by text, narrowed to a team', function () {
        fakeLinear(['searchIssues(term' => ['searchIssues' => ['nodes' => [linearIssue()], 'pageInfo' => ['hasNextPage' => false]]]]);

        $output = (new LinearSearchIssuesNode)->execute(Run::factory()->create(), linearConfig(['term' => 'login', 'team' => LINEAR_TEAM]), []);

        expect($output['count'])->toBe(1)
            ->and(linearVariables('searchIssues(term'))->toMatchArray(['term' => 'login', 'filter' => ['team' => ['id' => ['eq' => LINEAR_TEAM]]]]);
    });

    it('gets one issue by its identifier, and says when there is none', function () {
        fakeLinear(['issue(id: $id) { id identifier' => ['issue' => linearIssue()]]);

        expect((new LinearGetIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => 'ENG-1']), [])['identifier'])->toBe('ENG-1');

        fakeLinear(['issue(id: $id)' => ['issue' => null]]);

        expect(fn () => (new LinearGetIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => 'ENG-99']), []))->toThrow(RuntimeException::class, 'no issue "ENG-99"');
    });
});

describe('writing issues', function () {
    it('creates an issue from names, putting it in the team\'s first status when none is given', function () {
        fakeLinear([
            'teams(filter' => ['teams' => ['nodes' => [['id' => LINEAR_TEAM]]]],
            'workflowStates(filter' => ['workflowStates' => ['nodes' => [['id' => LINEAR_STATE]]]],
            'issueLabels(filter' => ['issueLabels' => ['nodes' => [['id' => 'l-other', 'team' => ['id' => 'other']], ['id' => 'l-mine', 'team' => ['id' => LINEAR_TEAM]]]]],
            'users(filter' => ['users' => ['nodes' => [['id' => LINEAR_USER]]]],
            'issueCreate(' => ['issueCreate' => ['success' => true, 'issue' => linearIssue('ENG-7')]],
        ]);

        $output = (new LinearCreateIssueNode)->execute(Run::factory()->create(), linearConfig([
            'team' => 'ENG', 'title' => 'Login fails', 'description' => 'Steps', 'state' => 'Backlog', 'priority' => 'urgent',
            'labels' => 'Bug', 'assignee' => 'Ada', 'due_date' => '2026-03-10T09:30:00Z', 'estimate' => '3',
        ]), []);

        expect($output['identifier'])->toBe('ENG-7');
        expect(linearVariables('issueCreate(')['input'])->toBe([
            'teamId' => LINEAR_TEAM, 'title' => 'Login fails', 'description' => 'Steps', 'stateId' => LINEAR_STATE, 'priority' => 1,
            'labelIds' => ['l-mine'], 'assigneeId' => LINEAR_USER, 'dueDate' => '2026-03-10', 'estimate' => 3,
        ]);
    });

    it('makes a sub-issue by looking the parent up', function () {
        fakeLinear([
            'issue(id: $id) { id }' => ['issue' => ['id' => 'parent-uuid']],
            'issueCreate(' => ['issueCreate' => ['success' => true, 'issue' => linearIssue()]],
        ]);

        (new LinearCreateIssueNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM, 'title' => 'Child', 'parent' => 'ENG-100']), []);

        expect(linearVariables('issueCreate(')['input']['parentId'])->toBe('parent-uuid');
    });

    it('fails with a sentence when a name matches nothing', function () {
        fakeLinear(['teams(filter' => ['teams' => ['nodes' => []]]]);

        (new LinearCreateIssueNode)->execute(Run::factory()->create(), linearConfig(['team' => 'Nope', 'title' => 'x']), []);
    })->throws(InvalidArgumentException::class, 'No Linear team matches "Nope"');

    it('fails when Linear refuses the mutation', function () {
        fakeLinear(['issueCreate(' => ['issueCreate' => ['success' => false, 'issue' => null]]]);

        (new LinearCreateIssueNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM, 'title' => 'x']), []);
    })->throws(RuntimeException::class, 'did not create the issue');

    it('refuses a priority it does not know', function () {
        (new LinearCreateIssueNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM, 'title' => 'x', 'priority' => 'asap']), []);
    })->throws(InvalidArgumentException::class, 'not a Linear priority');

    it('updates only what is filled in, finding the status in the issue\'s own team', function () {
        fakeLinear([
            'issue(id: $id) { team { id } }' => ['issue' => ['team' => ['id' => LINEAR_TEAM]]],
            'workflowStates(filter' => ['workflowStates' => ['nodes' => [['id' => LINEAR_STATE]]]],
            'issueLabels(filter' => ['issueLabels' => ['nodes' => [['id' => 'l-new', 'team' => null]]]],
            'issueUpdate(' => ['issueUpdate' => ['success' => true, 'issue' => linearIssue()]],
        ]);

        (new LinearUpdateIssueNode)->execute(Run::factory()->create(), linearConfig([
            'issue' => LINEAR_ISSUE, 'state' => 'Done', 'priority' => 'low', 'add_labels' => 'Regression',
        ]), []);

        expect(linearVariables('issueUpdate(')['input'])->toBe(['stateId' => LINEAR_STATE, 'priority' => 4, 'addedLabelIds' => ['l-new']]);
        expect(linearVariables('workflowStates(filter')['filter'])->toBe(['name' => ['eqIgnoreCase' => 'Done'], 'team' => ['id' => ['eq' => LINEAR_TEAM]]]);
    });

    it('can take the assignee off, which is a null and not a missing field', function () {
        fakeLinear(['issueUpdate(' => ['issueUpdate' => ['success' => true, 'issue' => linearIssue()]]]);

        (new LinearUpdateIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => LINEAR_ISSUE, 'unassign' => true]), []);

        $input = linearVariables('issueUpdate(')['input'];

        expect(array_key_exists('assigneeId', $input))->toBeTrue()
            ->and($input['assigneeId'])->toBeNull();
    });

    it('refuses an update that changes nothing', function () {
        (new LinearUpdateIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => LINEAR_ISSUE]), []);
    })->throws(InvalidArgumentException::class, 'at least one field');

    it('deletes to the trash, or for good when asked', function () {
        fakeLinear(['issueDelete(' => ['issueDelete' => ['success' => true]]]);

        (new LinearDeleteIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => LINEAR_ISSUE]), []);
        expect(linearVariables('issueDelete(')['id'])->toBe(LINEAR_ISSUE)
            ->and(linearVariables('issueDelete('))->not->toHaveKey('permanent');

        fakeLinear(['issueDelete(' => ['issueDelete' => ['success' => true]]]);

        expect((new LinearDeleteIssueNode)->execute(Run::factory()->create(), linearConfig(['issue' => LINEAR_ISSUE, 'permanent' => true]), []))->toBe(['deleted' => true, 'id' => LINEAR_ISSUE])
            ->and(linearVariables('issueDelete(')['permanent'])->toBeTrue();
    });
});

describe('relations', function () {
    it('lists them from both sides, and filters by direction', function () {
        $data = ['issue' => [
            'relations' => ['nodes' => [['id' => 'r1', 'type' => 'blocks', 'relatedIssue' => ['id' => 'a', 'identifier' => 'ENG-2', 'title' => 'B', 'url' => 'u', 'state' => ['name' => 'Todo']]]]],
            'inverseRelations' => ['nodes' => [['id' => 'r2', 'type' => 'blocks', 'issue' => ['id' => 'b', 'identifier' => 'ENG-3', 'title' => 'C', 'url' => 'u', 'state' => ['name' => 'Done']]], ['id' => 'r3', 'type' => 'related', 'issue' => ['id' => 'c', 'identifier' => 'ENG-4', 'title' => 'D', 'url' => 'u', 'state' => ['name' => 'Todo']]]]],
        ]];
        fakeLinear(['inverseRelations' => $data]);

        $all = (new LinearListIssueRelationsNode)->execute(Run::factory()->create(), linearConfig(['issue' => 'ENG-1']), []);
        $blockedBy = (new LinearListIssueRelationsNode)->execute(Run::factory()->create(), linearConfig(['issue' => 'ENG-1', 'direction' => 'blocked_by']), []);

        expect(array_column($all['relations'], 'direction'))->toBe(['blocks', 'blocked_by', 'related'])
            ->and($all['relations'][0])->toMatchArray(['id' => 'r1', 'issue_identifier' => 'ENG-2', 'issue_state' => 'Todo'])
            ->and(array_column($blockedBy['relations'], 'issue_identifier'))->toBe(['ENG-3']);
    });

    it('creates "blocked by" as the other issue blocking this one', function () {
        fakeLinear([
            'issue(id: $id) { id }' => fn (Request $request): array => ['issue' => ['id' => 'uuid-of-'.$request['variables']['id']]],
            'issueRelationCreate(' => ['issueRelationCreate' => ['success' => true, 'issueRelation' => ['id' => 'rel', 'type' => 'blocks', 'issue' => ['identifier' => 'ENG-2'], 'relatedIssue' => ['identifier' => 'ENG-1']]]],
        ]);

        $output = (new LinearCreateIssueRelationNode)->execute(Run::factory()->create(), linearConfig(['issue' => 'ENG-1', 'type' => 'blocked_by', 'related_issue' => 'ENG-2']), []);

        expect(linearVariables('issueRelationCreate(')['input'])->toBe(['issueId' => 'uuid-of-ENG-2', 'relatedIssueId' => 'uuid-of-ENG-1', 'type' => 'blocks'])
            ->and($output)->toMatchArray(['id' => 'rel', 'type' => 'blocks']);
    });

    it('updates and deletes a relation', function () {
        fakeLinear([
            'issueRelationUpdate(' => ['issueRelationUpdate' => ['success' => true, 'issueRelation' => ['id' => 'rel', 'type' => 'duplicate', 'issue' => ['identifier' => 'ENG-1'], 'relatedIssue' => ['identifier' => 'ENG-2']]]],
            'issueRelationDelete(' => ['issueRelationDelete' => ['success' => true]],
        ]);

        $updated = (new LinearUpdateIssueRelationNode)->execute(Run::factory()->create(), linearConfig(['relation_id' => 'rel', 'type' => 'duplicate']), []);
        $deleted = (new LinearDeleteIssueRelationNode)->execute(Run::factory()->create(), linearConfig(['relation_id' => 'rel']), []);

        expect($updated['type'])->toBe('duplicate')
            ->and(linearVariables('issueRelationUpdate(')['input'])->toBe(['type' => 'duplicate'])
            ->and($deleted)->toBe(['deleted' => true, 'id' => 'rel']);
    });
});

describe('comments', function () {
    it('lists, adds, edits and deletes comments', function () {
        $comment = ['id' => 'c1', 'body' => 'Looks good', 'url' => 'u', 'createdAt' => '2026-03-01T10:00:00Z', 'user' => ['id' => LINEAR_USER, 'name' => 'Ada', 'email' => 'ada@acme.com'], 'parent' => null, 'issue' => ['id' => LINEAR_ISSUE, 'identifier' => 'ENG-1']];
        fakeLinear([
            'comments(first' => ['issue' => ['comments' => ['nodes' => [$comment], 'pageInfo' => ['hasNextPage' => false]]]],
            'commentCreate(' => ['commentCreate' => ['success' => true, 'comment' => $comment]],
            'commentUpdate(' => ['commentUpdate' => ['success' => true, 'comment' => [...$comment, 'body' => 'Edited']]],
            'commentDelete(' => ['commentDelete' => ['success' => true]],
        ]);
        $run = Run::factory()->create();

        $listed = (new LinearListCommentsNode)->execute($run, linearConfig(['issue' => 'ENG-1']), []);
        $created = (new LinearCreateCommentNode)->execute($run, linearConfig(['issue' => LINEAR_ISSUE, 'body' => 'Looks good', 'parent_comment' => 'c0']), []);
        $updated = (new LinearUpdateCommentNode)->execute($run, linearConfig(['comment_id' => 'c1', 'body' => 'Edited']), []);
        $deleted = (new LinearDeleteCommentNode)->execute($run, linearConfig(['comment_id' => 'c1']), []);

        expect($listed['comments'][0])->toMatchArray(['id' => 'c1', 'body' => 'Looks good', 'author' => 'Ada', 'issue_identifier' => 'ENG-1'])
            ->and($created['id'])->toBe('c1')
            ->and(linearVariables('commentCreate(')['input'])->toBe(['issueId' => LINEAR_ISSUE, 'body' => 'Looks good', 'parentId' => 'c0'])
            ->and($updated['body'])->toBe('Edited')
            ->and(linearVariables('commentUpdate(')['input'])->toBe(['body' => 'Edited'])
            ->and($deleted)->toBe(['deleted' => true, 'id' => 'c1']);
    });
});

describe('projects and initiatives', function () {
    $project = ['id' => 'p1', 'name' => 'Website', 'description' => 'Relaunch', 'url' => 'u', 'progress' => 0.5, 'startDate' => '2026-03-01', 'targetDate' => '2026-06-01', 'status' => ['id' => 's1', 'name' => 'Started', 'type' => 'started'], 'lead' => ['id' => LINEAR_USER, 'name' => 'Ada', 'email' => 'a@x.com'], 'teams' => ['nodes' => [['id' => LINEAR_TEAM, 'key' => 'ENG', 'name' => 'Engineering']]]];

    it('lists projects, filtered by team, name and status', function () use ($project) {
        fakeLinear(['projects(filter' => ['projects' => ['nodes' => [$project, [...$project, 'id' => 'p2', 'name' => 'Docs', 'status' => ['id' => 's2', 'name' => 'Planned', 'type' => 'planned']]], 'pageInfo' => ['hasNextPage' => false]]]]);

        $output = (new LinearListProjectsNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM, 'query' => 'site', 'status' => 'started']), []);

        expect(array_column($output['projects'], 'name'))->toBe(['Website'])
            ->and($output['projects'][0])->toMatchArray(['status' => 'Started', 'lead' => 'Ada', 'teams' => ['Engineering'], 'target_date' => '2026-06-01'])
            ->and(linearVariables('projects(filter')['filter'])->toBe(['accessibleTeams' => ['some' => ['id' => ['eq' => LINEAR_TEAM]]], 'name' => ['containsIgnoreCase' => 'site']]);
    });

    it('creates, gets, updates and deletes a project', function () use ($project) {
        fakeLinear([
            'teams(filter' => ['teams' => ['nodes' => [['id' => LINEAR_TEAM]]]],
            'projectStatuses(filter' => ['projectStatuses' => ['nodes' => [['id' => 's1']]]],
            'projects(filter' => ['projects' => ['nodes' => [['id' => 'p1']]]],
            'users(filter' => ['users' => ['nodes' => [['id' => LINEAR_USER]]]],
            'projectCreate(' => ['projectCreate' => ['success' => true, 'project' => $project]],
            'projectUpdate(' => ['projectUpdate' => ['success' => true, 'project' => $project]],
            'projectDelete(' => ['projectDelete' => ['success' => true]],
            'project(id: $id)' => ['project' => $project],
        ]);
        $run = Run::factory()->create();

        $created = (new LinearCreateProjectNode)->execute($run, linearConfig(['name' => 'Website', 'teams' => 'ENG, Engineering', 'lead' => 'Ada', 'target_date' => '2026-06-01', 'priority' => 'high']), []);
        (new LinearUpdateProjectNode)->execute($run, linearConfig(['project' => 'Website', 'status' => 'Started', 'start_date' => '2026-03-01']), []);
        $got = (new LinearGetProjectNode)->execute($run, linearConfig(['project' => 'Website']), []);
        $deleted = (new LinearDeleteProjectNode)->execute($run, linearConfig(['project' => LINEAR_TEAM]), []);

        expect($created['name'])->toBe('Website')
            ->and(linearVariables('projectCreate(')['input'])->toBe(['name' => 'Website', 'teamIds' => [LINEAR_TEAM, LINEAR_TEAM], 'leadId' => LINEAR_USER, 'priority' => 2, 'targetDate' => '2026-06-01'])
            ->and(linearVariables('projectUpdate(')['input'])->toBe(['statusId' => 's1', 'startDate' => '2026-03-01'])
            ->and($got['id'])->toBe('p1')
            ->and($deleted['deleted'])->toBeTrue();
    });

    it('refuses a project with no team', function () {
        (new LinearCreateProjectNode)->execute(Run::factory()->create(), linearConfig(['name' => 'x', 'teams' => ' , ']), []);
    })->throws(InvalidArgumentException::class, 'at least one team');

    it('manages initiatives and links and unlinks their projects', function () {
        $initiative = ['id' => 'i1', 'name' => 'Grow EMEA', 'description' => 'd', 'url' => 'u', 'status' => 'Active', 'targetDate' => '2026-12-31', 'owner' => ['id' => LINEAR_USER, 'name' => 'Ada', 'email' => 'a@x.com'], 'projects' => ['nodes' => [['id' => 'p1', 'name' => 'Website']]]];
        fakeLinear([
            'initiatives(filter' => ['initiatives' => ['nodes' => [$initiative, [...$initiative, 'id' => 'i2', 'status' => 'Planned']], 'pageInfo' => ['hasNextPage' => false]]],
            'initiativeCreate(' => ['initiativeCreate' => ['success' => true, 'initiative' => $initiative]],
            'initiativeUpdate(' => ['initiativeUpdate' => ['success' => true]],
            'initiativeToProjectCreate(' => ['initiativeToProjectCreate' => ['success' => true]],
            'initiativeToProjects(filter' => ['initiativeToProjects' => ['nodes' => [['id' => 'link-1']]]],
            'initiativeToProjectDelete(' => ['initiativeToProjectDelete' => ['success' => true]],
            'initiativeDelete(' => ['initiativeDelete' => ['success' => true]],
            'initiative(id: $id)' => ['initiative' => $initiative],
        ]);
        $run = Run::factory()->create();

        $listed = (new LinearListInitiativesNode)->execute($run, linearConfig(['status' => 'active']), []);
        $created = (new LinearCreateInitiativeNode)->execute($run, linearConfig(['name' => 'Grow EMEA', 'status' => 'Active', 'target_date' => '2026-12-31']), []);
        $updated = (new LinearUpdateInitiativeNode)->execute($run, linearConfig([
            'initiative' => 'i1', 'status' => 'Completed', 'link_projects' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'unlink_projects' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ]), []);
        $got = (new LinearGetInitiativeNode)->execute($run, linearConfig(['initiative' => 'Grow EMEA']), []);
        (new LinearDeleteInitiativeNode)->execute($run, linearConfig(['initiative' => LINEAR_TEAM]), []);

        expect(array_column($listed['initiatives'], 'id'))->toBe(['i1'])
            ->and($created)->toMatchArray(['name' => 'Grow EMEA', 'status' => 'Active', 'projects' => ['Website']])
            ->and(linearVariables('initiativeToProjectCreate(')['input'])->toBe(['initiativeId' => 'i1', 'projectId' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'])
            ->and(linearVariables('initiativeToProjectDelete(')['id'])->toBe('link-1')
            ->and($updated['id'])->toBe('i1')
            ->and($got['name'])->toBe('Grow EMEA');
    });

    it('posts, lists, edits and deletes project updates', function () {
        $update = ['id' => 'u1', 'body' => 'On track', 'health' => 'onTrack', 'url' => 'u', 'createdAt' => '2026-03-01T10:00:00Z', 'user' => ['id' => LINEAR_USER, 'name' => 'Ada'], 'project' => ['id' => 'p1', 'name' => 'Website']];
        fakeLinear([
            'projects(filter' => ['projects' => ['nodes' => [['id' => 'p1']]]],
            'projectUpdates(filter' => ['projectUpdates' => ['nodes' => [$update], 'pageInfo' => ['hasNextPage' => false]]],
            'projectUpdateCreate(' => ['projectUpdateCreate' => ['success' => true, 'projectUpdate' => $update]],
            'projectUpdateUpdate(' => ['projectUpdateUpdate' => ['success' => true, 'projectUpdate' => [...$update, 'health' => 'atRisk']]],
            'projectUpdateDelete(' => ['projectUpdateDelete' => ['success' => true]],
        ]);
        $run = Run::factory()->create();

        $created = (new LinearCreateStatusUpdateNode)->execute($run, linearConfig(['project' => 'Website', 'body' => 'On track', 'health' => 'onTrack']), []);
        $listed = (new LinearListStatusUpdatesNode)->execute($run, linearConfig(['project' => 'Website']), []);
        $updated = (new LinearUpdateStatusUpdateNode)->execute($run, linearConfig(['update_id' => 'u1', 'health' => 'atRisk']), []);
        $deleted = (new LinearDeleteStatusUpdateNode)->execute($run, linearConfig(['update_id' => 'u1']), []);

        expect($created)->toMatchArray(['health' => 'onTrack', 'project' => 'Website', 'author' => 'Ada'])
            ->and(linearVariables('projectUpdateCreate(')['input'])->toBe(['projectId' => 'p1', 'body' => 'On track', 'health' => 'onTrack'])
            ->and($listed['count'])->toBe(1)
            ->and($updated['health'])->toBe('atRisk')
            ->and($deleted['deleted'])->toBeTrue();
    });
});

describe('lookups', function () {
    it('lists teams, statuses, labels, people and cycles, and the current user', function () {
        fakeLinear([
            'teams(first' => ['teams' => ['nodes' => [['id' => LINEAR_TEAM, 'key' => 'ENG', 'name' => 'Engineering', 'description' => null, 'private' => false]], 'pageInfo' => ['hasNextPage' => false]]],
            'workflowStates(filter' => ['workflowStates' => ['nodes' => [['id' => LINEAR_STATE, 'name' => 'Done', 'type' => 'completed', 'color' => '#0f0', 'position' => 3, 'team' => ['id' => LINEAR_TEAM, 'key' => 'ENG', 'name' => 'Engineering']]], 'pageInfo' => ['hasNextPage' => false]]],
            'issueLabels(filter' => ['issueLabels' => ['nodes' => [['id' => 'l1', 'name' => 'Bug', 'color' => '#f00', 'description' => null, 'team' => null]], 'pageInfo' => ['hasNextPage' => false]]],
            'users(filter' => ['users' => ['nodes' => [['id' => LINEAR_USER, 'name' => 'Ada', 'displayName' => 'ada', 'email' => 'ada@acme.com', 'active' => true, 'admin' => false, 'url' => 'u']], 'pageInfo' => ['hasNextPage' => false]]],
            'cycles(filter' => ['cycles' => ['nodes' => [['id' => 'c1', 'number' => 4, 'name' => null, 'startsAt' => '2026-03-01', 'endsAt' => '2026-03-14', 'completedAt' => null, 'team' => ['id' => LINEAR_TEAM, 'key' => 'ENG', 'name' => 'Engineering']]], 'pageInfo' => ['hasNextPage' => false]]],
            'viewer' => ['viewer' => ['id' => LINEAR_USER, 'name' => 'Ada', 'displayName' => 'ada', 'email' => 'ada@acme.com', 'active' => true, 'admin' => true, 'url' => 'u', 'organization' => ['id' => 'o1', 'name' => 'Acme', 'urlKey' => 'acme']]],
        ]);
        $run = Run::factory()->create();

        expect((new LinearListTeamsNode)->execute($run, linearConfig(), [])['teams'][0])->toMatchArray(['key' => 'ENG', 'name' => 'Engineering'])
            ->and((new LinearListWorkflowStatesNode)->execute($run, linearConfig(['team' => LINEAR_TEAM]), [])['states'][0])->toMatchArray(['name' => 'Done', 'type' => 'completed', 'team_key' => 'ENG'])
            ->and((new LinearListLabelsNode)->execute($run, linearConfig(), [])['labels'][0])->toMatchArray(['name' => 'Bug', 'team' => null])
            ->and((new LinearListUsersNode)->execute($run, linearConfig(['query' => 'ada']), [])['users'][0])->toMatchArray(['name' => 'Ada', 'email' => 'ada@acme.com'])
            ->and((new LinearListCyclesNode)->execute($run, linearConfig(), [])['cycles'][0])->toMatchArray(['number' => 4, 'team_key' => 'ENG'])
            ->and((new LinearGetMeNode)->execute($run, linearConfig(), []))->toMatchArray(['name' => 'Ada', 'admin' => true, 'organization' => 'Acme', 'organization_key' => 'acme']);

        expect(linearVariables('users(filter')['filter'])->toBe(['active' => ['eq' => true], 'or' => [['name' => ['containsIgnoreCase' => 'ada']], ['email' => ['containsIgnoreCase' => 'ada']]]]);
    });
});

describe('errors', function () {
    it('turns a GraphQL error into a sentence, even on a 200', function () {
        Http::fake(['api.linear.app/graphql' => Http::response(['errors' => [['message' => 'Argument Validation Error', 'extensions' => ['userPresentableMessage' => 'Team not found']]]])]);

        (new LinearGetMeNode)->execute(Run::factory()->create(), linearConfig(), []);
    })->throws(RuntimeException::class, 'Linear API error: Team not found');

    it('says what the status was when there is no error body', function () {
        Http::fake(['api.linear.app/graphql' => Http::response('Bad gateway', 502)]);

        (new LinearGetMeNode)->execute(Run::factory()->create(), linearConfig(), []);
    })->throws(RuntimeException::class, 'HTTP 502');
});

describe('as triggers', function () {
    afterEach(fn () => Carbon::setTestNow());

    it('starts a flow for each new issue and each change to an existing one, not for a creation edit', function () {
        $new = new LinearListNewIssuesNode;
        $updated = new LinearListUpdatedIssuesNode;

        expect($new)->toBeInstanceOf(SupportsTriggerMode::class)
            ->and($new->triggerName())->toBe('Linear: New issue')
            ->and($updated->triggerName())->toBe('Linear: Issue updated')
            ->and($new->triggerPollMinutes())->toBe(1)
            ->and($new->triggerListsNewestFirst())->toBeTrue()
            ->and($new->triggerReadConfig(['team' => 'ENG']))->toMatchArray(['team' => 'ENG', 'created_within_minutes' => 360, 'limit' => 250]);

        $issues = [
            [...linearIssue('ENG-1'), 'id' => 'a', 'created_at' => '2026-03-01T10:00:00Z', 'updated_at' => '2026-03-01T10:00:10Z'],
            [...linearIssue('ENG-2'), 'id' => 'b', 'created_at' => '2026-03-01T10:00:00Z', 'updated_at' => '2026-03-01T11:00:00Z'],
        ];

        expect(array_column($updated->triggerItems(['issues' => $issues], []), 'id'))->toBe(['b'])
            ->and($updated->triggerItemId($issues[1]))->toBe('b:2026-03-01T11:00:00Z')
            ->and($new->triggerItemId($issues[1]))->toBe('b');
    });

    it('asks only for issues made within the window, in the chosen team', function () {
        Carbon::setTestNow('2026-03-01 12:00:00');
        fakeLinear(['issues(filter' => ['issues' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]);

        (new LinearListNewIssuesNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM, 'created_within_minutes' => 60]), []);

        expect(linearVariables('issues(filter')['filter'])->toBe(['team' => ['id' => ['eq' => LINEAR_TEAM]], 'createdAt' => ['gte' => Carbon::parse('2026-03-01 11:00:00')->toIso8601String()]]);
        expect(linearSent()[0]['query'])->toContain('orderBy: createdAt');
    });

    it('lists new comments of a team', function () {
        Carbon::setTestNow('2026-03-01 12:00:00');
        fakeLinear(['comments(filter' => ['comments' => ['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]]]);

        (new LinearListNewCommentsNode)->execute(Run::factory()->create(), linearConfig(['team' => LINEAR_TEAM]), []);

        expect(linearVariables('comments(filter')['filter']['issue'])->toBe(['team' => ['id' => ['eq' => LINEAR_TEAM]]]);
    });

    it('is polled like any app trigger: a baseline first, then one run for each new issue', function () {
        Queue::fake([FireTriggerEvent::class]);

        $owner = User::factory()->create();
        $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
        $workflow = Workflow::factory()->forWorkspace($workspace)->create();
        $credential = ConnectorCredential::factory()->forWorkspace($workspace)
            ->forConnector(Connector::query()->firstWhere('key', 'linear') ?? Connector::factory()->create(['key' => 'linear']))
            ->create(['data' => ['access_token' => 'lin_test'], 'created_by' => $owner->id]);

        $workflow->replaceGraph([
            'nodes' => [
                ['key' => 'start', 'type' => 'linear_list_new_issues', 'config' => ['credential_id' => $credential->id, 'activate' => true, 'team' => LINEAR_TEAM]],
                ['key' => 'next', 'type' => 'transform', 'config' => ['mapping' => []]],
            ],
            'edges' => [['from' => 'start', 'to' => 'next']],
        ]);
        $workflow->fresh()->publishVersion(publisher: $owner);

        $trigger = Trigger::query()->where('target_id', $workflow->id)->where('trigger_node_key', 'start')->firstOrFail();
        $poll = fn (array $issues) => fakeLinear(['issues(filter' => ['issues' => ['nodes' => $issues, 'pageInfo' => ['hasNextPage' => false]]]]);

        // Newest first, as Linear lists them.
        $poll([linearIssue('ENG-1', ['id' => 'i1'])]);
        dispatch_sync(new PollTrigger($trigger));
        expect($trigger->events()->where('status', 'queued')->count())->toBe(0);

        $poll([linearIssue('ENG-3', ['id' => 'i3']), linearIssue('ENG-2', ['id' => 'i2']), linearIssue('ENG-1', ['id' => 'i1'])]);
        dispatch_sync(new PollTrigger($trigger));

        $events = $trigger->events()->where('status', 'queued')->get();

        expect($events)->toHaveCount(2)
            ->and($events->pluck('payload.identifier')->sort()->values()->all())->toBe(['ENG-2', 'ENG-3']);
    });
});
