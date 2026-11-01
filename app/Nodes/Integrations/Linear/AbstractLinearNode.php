<?php

namespace App\Nodes\Integrations\Linear;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Shared request, error handling, name lookups and result shaping for the
 * Linear node family. Resolves the access token via
 * `ResolvesConnectorCredential` and speaks Linear's GraphQL API.
 *
 * Linear answers a failed query with HTTP 200 and an `errors` list as often as
 * with an error status, so both are checked. Every node hands back plain,
 * flattened records (`state: "In Progress"`, `labels: ["Bug"]`) with the ids
 * next to the names, which is what a following step usually wants.
 *
 * A field that takes a team, status, label, person or project accepts either
 * its id (what the editor's dropdown fills in) or its name, so a value mapped
 * from an earlier step ("Engineering", "ada@acme.com") works as it is.
 */
abstract class AbstractLinearNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.linear.app/graphql';

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * The most records Linear returns in one page.
     */
    protected const int PAGE_SIZE = 100;

    /**
     * An issue as every issue node asks for it.
     */
    protected const string ISSUE_FIELDS = 'id identifier number title description url priority priorityLabel estimate dueDate branchName createdAt updatedAt completedAt canceledAt archivedAt
        state { id name type } team { id key name } project { id name } assignee { id name email } creator { id name }
        labels { nodes { id name } } parent { id identifier } cycle { id number name }';

    protected const string PROJECT_FIELDS = 'id name description url slugId icon color priority startDate targetDate completedAt canceledAt createdAt updatedAt progress
        status { id name type } lead { id name email } teams { nodes { id key name } }';

    protected const string INITIATIVE_FIELDS = 'id name description url status targetDate createdAt updatedAt completedAt owner { id name email }
        projects { nodes { id name } }';

    protected const string COMMENT_FIELDS = 'id body url createdAt updatedAt editedAt user { id name email } parent { id } issue { id identifier }';

    protected const string STATUS_UPDATE_FIELDS = 'id body health url createdAt updatedAt user { id name } project { id name }';

    /**
     * Linear's priority numbers by name. Anything a person might type for
     * "none" is 0.
     *
     * @var array<string, int>
     */
    private const array PRIORITIES = ['none' => 0, 'no priority' => 0, 'urgent' => 1, 'high' => 2, 'medium' => 3, 'normal' => 3, 'low' => 4];

    public function category(): string
    {
        return 'linear';
    }

    /**
     * Run one query or mutation and return its `data`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $variables  null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function graphql(Run $run, array $config, string $query, array $variables = []): array
    {
        $variables = array_filter($variables, fn (mixed $value): bool => $value !== null);

        $response = Http::withToken($this->resolveAccessToken($run, $config))
            ->acceptJson()
            ->timeout(30)
            ->post(self::API_URL, ['query' => $query, 'variables' => $variables === [] ? new \stdClass : $variables]);

        $errors = (array) ($response->json('errors') ?? []);

        if ($errors !== []) {
            $messages = collect($errors)->map(fn (mixed $error): string => (string) ($error['extensions']['userPresentableMessage'] ?? $error['message'] ?? 'Unknown error'))->unique()->implode('; ');

            throw new RuntimeException("Linear API error: {$messages}");
        }

        if ($response->failed()) {
            throw new RuntimeException("Linear API error: HTTP {$response->status()}.");
        }

        return (array) ($response->json('data') ?? []);
    }

    /**
     * Read a connection (`nodes` and `pageInfo`) page by page until `$limit`
     * records are in or it ends. The query must take `$first: Int` and
     * `$after: String` and pass them to the connection at `$path`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $variables
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $query, array $variables, string $path, int $limit): array
    {
        $results = [];
        $cursor = null;

        do {
            $data = $this->graphql($run, $config, $query, [...$variables, 'first' => min(self::PAGE_SIZE, $limit - count($results)), 'after' => $cursor]);

            foreach ((array) data_get($data, "{$path}.nodes", []) as $node) {
                if (is_array($node)) {
                    $results[] = $node;
                }
            }

            $cursor = data_get($data, "{$path}.pageInfo.hasNextPage") === true ? data_get($data, "{$path}.pageInfo.endCursor") : null;
        } while ($cursor !== null && count($results) < $limit);

        return array_slice($results, 0, $limit);
    }

    /**
     * Read the `success` flag of a mutation payload, so a refusal is an error.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    protected function assertSucceeded(array $data, string $mutation, string $what): void
    {
        if (data_get($data, "{$mutation}.success") !== true) {
            throw new RuntimeException("Linear did not {$what}.");
        }
    }

    /**
     * An id from a config field.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A comma-separated field (or a list) as a list of trimmed, non-empty strings.
     *
     * @return list<string>
     */
    protected function csv(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * A priority from its number or name; null when none was given.
     *
     * @throws InvalidArgumentException
     */
    protected function priority(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value >= 0 && (int) $value <= 4) {
            return (int) $value;
        }

        return self::PRIORITIES[strtolower(trim((string) $value))] ?? throw new InvalidArgumentException("\"{$value}\" is not a Linear priority. Use Urgent, High, Medium, Low or No priority.");
    }

    /**
     * A date as Linear's date-only text (2026-03-01); null when none was given.
     *
     * @throws InvalidArgumentException
     */
    protected function date(mixed $value, string $label): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            throw new InvalidArgumentException("{$label} isn't a date Linear can read. Try 2026-03-01.");
        }
    }

    /**
     * The id of a team, status, label, person, project, cycle or initiative
     * from its id or its name; null when nothing was given.
     *
     * @param  'team'|'state'|'label'|'user'|'project'|'project_status'|'initiative'|'cycle'  $kind
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when no such record exists
     */
    protected function resolveId(Run $run, array $config, string $kind, mixed $value, ?string $teamId = null): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match(self::UUID_PATTERN, $value) === 1) {
            return $value;
        }

        [$collection, $filterType, $filter] = match ($kind) {
            'team' => ['teams', 'TeamFilter', ['or' => [['key' => ['eqIgnoreCase' => $value]], ['name' => ['eqIgnoreCase' => $value]]]]],
            'state' => ['workflowStates', 'WorkflowStateFilter', ['name' => ['eqIgnoreCase' => $value], ...($teamId === null ? [] : ['team' => ['id' => ['eq' => $teamId]]])]],
            'label' => ['issueLabels', 'IssueLabelFilter', ['name' => ['eqIgnoreCase' => $value]]],
            'user' => ['users', 'UserFilter', ['or' => [['email' => ['eqIgnoreCase' => $value]], ['name' => ['eqIgnoreCase' => $value]], ['displayName' => ['eqIgnoreCase' => $value]]]]],
            'project' => ['projects', 'ProjectFilter', ['name' => ['eqIgnoreCase' => $value]]],
            'project_status' => ['projectStatuses', 'ProjectStatusFilter', ['name' => ['eqIgnoreCase' => $value]]],
            'initiative' => ['initiatives', 'InitiativeFilter', ['name' => ['eqIgnoreCase' => $value]]],
            'cycle' => ['cycles', 'CycleFilter', ['number' => ['eq' => (int) $value], ...($teamId === null ? [] : ['team' => ['id' => ['eq' => $teamId]]])]],
        };

        $data = $this->graphql($run, $config, "query(\$filter: {$filterType}) { {$collection}(filter: \$filter, first: 25) { nodes { id ".($kind === 'label' ? 'team { id }' : '').' } } }', ['filter' => $filter]);
        $nodes = (array) data_get($data, "{$collection}.nodes", []);

        // A label of the issue's own team beats a workspace label of the same name.
        $match = collect($nodes)->first(fn (array $node): bool => $teamId !== null && data_get($node, 'team.id') === $teamId) ?? ($nodes[0] ?? null);

        return is_array($match) && isset($match['id'])
            ? (string) $match['id']
            : throw new InvalidArgumentException("No Linear {$kind} matches \"{$value}\". Check the spelling, or pick it from the list.");
    }

    /**
     * The id of an issue from its id or identifier (ENG-123).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when there is no such issue
     */
    protected function issueId(Run $run, array $config, mixed $issue, string $label = 'The issue'): string
    {
        $issue = trim((string) $issue);

        if ($issue === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        if (preg_match(self::UUID_PATTERN, $issue) === 1) {
            return $issue;
        }

        $id = data_get($this->graphql($run, $config, 'query($id: String!) { issue(id: $id) { id } }', ['id' => $issue]), 'issue.id');

        return is_string($id) ? $id : throw new InvalidArgumentException("{$label} \"{$issue}\" was not found in Linear.");
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 250): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * Several labels from ids or names.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function resolveLabelIds(Run $run, array $config, mixed $value, ?string $teamId): array
    {
        return array_values(array_filter(array_map(fn (string $label): ?string => $this->resolveId($run, $config, 'label', $label, $teamId), $this->csv($value))));
    }

    /**
     * The team an issue belongs to, needed to find a status or label by name.
     *
     * @param  array<string, mixed>  $config
     */
    protected function teamOfIssue(Run $run, array $config, string $issue): ?string
    {
        $data = $this->graphql($run, $config, 'query($id: String!) { issue(id: $id) { team { id } } }', ['id' => $issue]);

        return data_get($data, 'issue.team.id');
    }

    /**
     * The Linear filter for the issue list nodes. Every setting is optional.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function issueFilter(Run $run, array $config, ?string $createdSince = null, ?string $updatedSince = null): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);
        $filter = [];

        if ($teamId !== null) {
            $filter['team'] = ['id' => ['eq' => $teamId]];
        }

        if (($state = $this->resolveId($run, $config, 'state', $config['state'] ?? null, $teamId)) !== null) {
            $filter['state'] = ['id' => ['eq' => $state]];
        }

        if (($project = $this->resolveId($run, $config, 'project', $config['project'] ?? null)) !== null) {
            $filter['project'] = ['id' => ['eq' => $project]];
        }

        if (($assignee = $this->resolveId($run, $config, 'user', $config['assignee'] ?? null)) !== null) {
            $filter['assignee'] = ['id' => ['eq' => $assignee]];
        }

        if (($priority = $this->priority($config['priority'] ?? null)) !== null) {
            $filter['priority'] = ['eq' => $priority];
        }

        // Matches an issue with at least one of the labels.
        if (($labels = $this->resolveLabelIds($run, $config, $config['labels'] ?? null, $teamId)) !== []) {
            $filter['labels'] = ['some' => ['id' => ['in' => $labels]]];
        }

        if ($createdSince !== null) {
            $filter['createdAt'] = ['gte' => $createdSince];
        }

        if ($updatedSince !== null) {
            $filter['updatedAt'] = ['gte' => $updatedSince];
        }

        return $filter;
    }

    /**
     * The settings every issue list node takes: where, what and how many.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function issueFilterFields(bool $teamRequired = false): array
    {
        return [
            'team' => Field::dynamic('Team', 'linear.teams', $teamRequired ? 'The team to read issues from.' : 'Only issues of this team. Leave empty for every team.', 'Engineering'),
            'state' => Field::dynamic('Status', 'linear.states', 'Only issues in this status, e.g. In Progress.', 'In Progress', uses: ['team']),
            'project' => Field::dynamic('Project', 'linear.projects', 'Only issues in this project.', 'Website relaunch'),
            'priority' => Field::select('Priority', ['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'none' => 'No priority'], 'Only issues with this priority.'),
            'labels' => Field::dynamic('Labels', 'linear.labels', 'Issues with at least one of these labels.', 'Bug', multiple: true, uses: ['team']),
            'assignee' => Field::dynamic('Assignee', 'linear.users', 'Only issues assigned to this person.', 'ada@acme.com'),
        ];
    }

    /**
     * The `$first`/`$after` and filter parts of an issue list query.
     */
    protected function issuesQuery(string $orderBy = 'updatedAt'): string
    {
        return 'query($filter: IssueFilter, $first: Int, $after: String) { issues(filter: $filter, first: $first, after: $after, orderBy: '.$orderBy.') { nodes { '.self::ISSUE_FIELDS.' } pageInfo { hasNextPage endCursor } } }';
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function shapeIssue(array $issue): array
    {
        return [
            'id' => $issue['id'] ?? null,
            'identifier' => $issue['identifier'] ?? null,
            'number' => $issue['number'] ?? null,
            'title' => $issue['title'] ?? null,
            'description' => $issue['description'] ?? null,
            'url' => $issue['url'] ?? null,
            'priority' => $issue['priority'] ?? 0,
            'priority_label' => $issue['priorityLabel'] ?? null,
            'estimate' => $issue['estimate'] ?? null,
            'state' => data_get($issue, 'state.name'),
            'state_id' => data_get($issue, 'state.id'),
            'state_type' => data_get($issue, 'state.type'),
            'team' => data_get($issue, 'team.name'),
            'team_key' => data_get($issue, 'team.key'),
            'team_id' => data_get($issue, 'team.id'),
            'project' => data_get($issue, 'project.name'),
            'project_id' => data_get($issue, 'project.id'),
            'assignee' => data_get($issue, 'assignee.name'),
            'assignee_id' => data_get($issue, 'assignee.id'),
            'assignee_email' => data_get($issue, 'assignee.email'),
            'creator' => data_get($issue, 'creator.name'),
            'labels' => array_values(array_map(fn (array $label): string => (string) $label['name'], (array) data_get($issue, 'labels.nodes', []))),
            'label_ids' => array_values(array_map(fn (array $label): string => (string) $label['id'], (array) data_get($issue, 'labels.nodes', []))),
            'parent_identifier' => data_get($issue, 'parent.identifier'),
            'cycle' => data_get($issue, 'cycle.number'),
            'due_date' => $issue['dueDate'] ?? null,
            'branch_name' => $issue['branchName'] ?? null,
            'created_at' => $issue['createdAt'] ?? null,
            'updated_at' => $issue['updatedAt'] ?? null,
            'completed_at' => $issue['completedAt'] ?? null,
            'canceled_at' => $issue['canceledAt'] ?? null,
            'archived_at' => $issue['archivedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    protected function shapeProject(array $project): array
    {
        return [
            'id' => $project['id'] ?? null,
            'name' => $project['name'] ?? null,
            'description' => $project['description'] ?? null,
            'url' => $project['url'] ?? null,
            'status' => data_get($project, 'status.name'),
            'status_type' => data_get($project, 'status.type'),
            'priority' => $project['priority'] ?? null,
            'progress' => $project['progress'] ?? null,
            'start_date' => $project['startDate'] ?? null,
            'target_date' => $project['targetDate'] ?? null,
            'lead' => data_get($project, 'lead.name'),
            'lead_id' => data_get($project, 'lead.id'),
            'teams' => array_values(array_map(fn (array $team): string => (string) ($team['name'] ?? ''), (array) data_get($project, 'teams.nodes', []))),
            'team_ids' => array_values(array_map(fn (array $team): string => (string) ($team['id'] ?? ''), (array) data_get($project, 'teams.nodes', []))),
            'created_at' => $project['createdAt'] ?? null,
            'updated_at' => $project['updatedAt'] ?? null,
            'completed_at' => $project['completedAt'] ?? null,
            'canceled_at' => $project['canceledAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $initiative
     * @return array<string, mixed>
     */
    protected function shapeInitiative(array $initiative): array
    {
        return [
            'id' => $initiative['id'] ?? null,
            'name' => $initiative['name'] ?? null,
            'description' => $initiative['description'] ?? null,
            'url' => $initiative['url'] ?? null,
            'status' => $initiative['status'] ?? null,
            'target_date' => $initiative['targetDate'] ?? null,
            'owner' => data_get($initiative, 'owner.name'),
            'owner_id' => data_get($initiative, 'owner.id'),
            'projects' => array_values(array_map(fn (array $project): string => (string) ($project['name'] ?? ''), (array) data_get($initiative, 'projects.nodes', []))),
            'project_ids' => array_values(array_map(fn (array $project): string => (string) ($project['id'] ?? ''), (array) data_get($initiative, 'projects.nodes', []))),
            'created_at' => $initiative['createdAt'] ?? null,
            'updated_at' => $initiative['updatedAt'] ?? null,
            'completed_at' => $initiative['completedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $comment
     * @return array<string, mixed>
     */
    protected function shapeComment(array $comment): array
    {
        return [
            'id' => $comment['id'] ?? null,
            'body' => $comment['body'] ?? null,
            'url' => $comment['url'] ?? null,
            'author' => data_get($comment, 'user.name'),
            'author_id' => data_get($comment, 'user.id'),
            'author_email' => data_get($comment, 'user.email'),
            'parent_id' => data_get($comment, 'parent.id'),
            'issue_id' => data_get($comment, 'issue.id'),
            'issue_identifier' => data_get($comment, 'issue.identifier'),
            'created_at' => $comment['createdAt'] ?? null,
            'updated_at' => $comment['updatedAt'] ?? null,
            'edited_at' => $comment['editedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    protected function shapeStatusUpdate(array $update): array
    {
        return [
            'id' => $update['id'] ?? null,
            'body' => $update['body'] ?? null,
            'health' => $update['health'] ?? null,
            'url' => $update['url'] ?? null,
            'author' => data_get($update, 'user.name'),
            'project' => data_get($update, 'project.name'),
            'project_id' => data_get($update, 'project.id'),
            'created_at' => $update['createdAt'] ?? null,
            'updated_at' => $update['updatedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function shapeUser(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'display_name' => $user['displayName'] ?? null,
            'email' => $user['email'] ?? null,
            'active' => $user['active'] ?? null,
            'admin' => $user['admin'] ?? null,
            'url' => $user['url'] ?? null,
        ];
    }
}
