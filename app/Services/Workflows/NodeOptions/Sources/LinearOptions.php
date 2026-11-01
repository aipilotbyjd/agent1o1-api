<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\ConnectionException;

/**
 * Linear teams, and the statuses, labels, people, projects and initiatives
 * of the workspace. Statuses and labels follow the node's `team` field when
 * it is set. Linear pages by cursor and filters by name itself.
 */
class LinearOptions extends HttpOptionsSource
{
    private const string API_URL = 'https://api.linear.app/graphql';

    private const int PAGE_SIZE = 50;

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function sources(): array
    {
        return ['linear.teams', 'linear.states', 'linear.labels', 'linear.users', 'linear.projects', 'linear.initiatives'];
    }

    protected function appName(): string
    {
        return 'Linear';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'linear.teams' => $this->page($query, 'teams', 'TeamFilter', $this->nameFilter($query, 'name', 'key'), 'id key name description', fn (array $team): NodeOption => new NodeOption((string) $team['id'], (string) $team['name'], $team['key'] ?? null)),
            'linear.states' => $this->page($query, 'workflowStates', 'WorkflowStateFilter', [...$this->teamFilter($query), ...$this->nameFilter($query, 'name')], 'id name type team { name }', fn (array $state): NodeOption => new NodeOption((string) $state['id'], (string) $state['name'], str_replace('_', ' ', (string) ($state['type'] ?? '')))),
            'linear.labels' => $this->labels($query),
            'linear.users' => $this->page($query, 'users', 'UserFilter', ['active' => ['eq' => true], ...$this->nameFilter($query, 'name', 'email')], 'id name displayName email', fn (array $user): NodeOption => new NodeOption((string) $user['id'], (string) ($user['name'] ?? $user['displayName'] ?? $user['id']), $user['email'] ?? null)),
            'linear.projects' => $this->page($query, 'projects', 'ProjectFilter', $this->nameFilter($query, 'name'), 'id name status { name }', fn (array $project): NodeOption => new NodeOption((string) $project['id'], (string) $project['name'], $project['status']['name'] ?? null)),
            default => $this->page($query, 'initiatives', 'InitiativeFilter', $this->nameFilter($query, 'name'), 'id name status', fn (array $initiative): NodeOption => new NodeOption((string) $initiative['id'], (string) $initiative['name'], $initiative['status'] ?? null)),
        };
    }

    /**
     * Labels of the node's team and the workspace's own, or all of them.
     */
    private function labels(NodeOptionsQuery $query): NodeOptionsPage
    {
        $teamId = $this->teamId($query);
        $filter = $this->nameFilter($query, 'name');

        if ($teamId !== null) {
            $scope = ['or' => [['team' => ['id' => ['eq' => $teamId]]], ['team' => ['null' => true]]]];
            $filter = $filter === [] ? $scope : ['and' => [$filter, $scope]];
        }

        return $this->page($query, 'issueLabels', 'IssueLabelFilter', $filter, 'id name team { name }', fn (array $label): NodeOption => new NodeOption((string) $label['id'], (string) $label['name'], $label['team']['name'] ?? 'Workspace'));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @param  callable(array<string, mixed>): NodeOption  $option
     */
    private function page(NodeOptionsQuery $query, string $collection, string $filterType, array $filter, string $fields, callable $option): NodeOptionsPage
    {
        $data = $this->graphql($query, "query(\$filter: {$filterType}, \$first: Int, \$after: String) { {$collection}(filter: \$filter, first: ".self::PAGE_SIZE.", after: \$after) { nodes { {$fields} } pageInfo { hasNextPage endCursor } } }", [
            'filter' => $filter === [] ? null : $filter,
            'after' => $query->cursor,
        ]);

        $connection = (array) ($data[$collection] ?? []);
        $options = collect((array) ($connection['nodes'] ?? []))
            ->filter(fn (mixed $node): bool => is_array($node) && isset($node['id']))
            ->map(fn (array $node): NodeOption => $option($node))
            ->values();

        return new NodeOptionsPage($options, ($connection['pageInfo']['hasNextPage'] ?? false) === true ? ($connection['pageInfo']['endCursor'] ?? null) : null);
    }

    /**
     * The search text as a name filter over one or more fields.
     *
     * @return array<string, mixed>
     */
    private function nameFilter(NodeOptionsQuery $query, string ...$fields): array
    {
        if ($query->search === null) {
            return [];
        }

        $terms = array_map(fn (string $field): array => [$field => ['containsIgnoreCase' => $query->search]], $fields);

        return count($terms) === 1 ? $terms[0] : ['or' => $terms];
    }

    /**
     * Narrows statuses to the node's team, when it names one.
     *
     * @return array<string, mixed>
     */
    private function teamFilter(NodeOptionsQuery $query): array
    {
        $teamId = $this->teamId($query);

        return $teamId === null ? [] : ['team' => ['id' => ['eq' => $teamId]]];
    }

    /**
     * The id of the node's `team` field, which holds an id, a name or a key.
     */
    private function teamId(NodeOptionsQuery $query): ?string
    {
        $team = $query->configString('team');

        if ($team === null || str_contains($team, '{{')) {
            return null;
        }

        if (preg_match(self::UUID_PATTERN, $team) === 1) {
            return $team;
        }

        $data = $this->graphql($query, 'query($filter: TeamFilter) { teams(filter: $filter, first: 1) { nodes { id } } }', [
            'filter' => ['or' => [['key' => ['eqIgnoreCase' => $team]], ['name' => ['eqIgnoreCase' => $team]]]],
        ]);

        return $data['teams']['nodes'][0]['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $variables  null values are dropped
     * @return array<string, mixed>
     *
     * @throws ConnectorException
     */
    private function graphql(NodeOptionsQuery $query, string $document, array $variables): array
    {
        $variables = array_filter($variables, fn (mixed $value): bool => $value !== null);

        try {
            $response = $this->http($query)->post(self::API_URL, ['query' => $document, 'variables' => $variables === [] ? new \stdClass : $variables]);
        } catch (ConnectionException) {
            throw new ConnectorException("Couldn't reach {$this->appName()}. Try again in a moment.");
        }

        // Linear answers a rejected query with HTTP 200 and `errors`.
        if ($response->successful() && ! empty($response->json('errors'))) {
            $this->logFailure(['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            throw new ConnectorException("Couldn't load options from {$this->appName()}.");
        }

        $this->ensureSuccessful($response, 'graphql');

        return (array) ($response->json('data') ?? []);
    }
}
