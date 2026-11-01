<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Jira sites, projects, issue types, priorities, statuses, people, labels,
 * saved filters, groups, link types and transitions, and Service Management
 * desks and request types. Everything reads the site the node's `site` field
 * names, or the account's first one. Jira pages by `startAt`; a search
 * filters the page it asked Jira for, or is passed to Jira where it can be.
 */
class JiraOptions extends HttpOptionsSource
{
    private const string GATEWAY = 'https://api.atlassian.com';

    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return [
            'jira.sites', 'jira.projects', 'jira.issue_types', 'jira.priorities', 'jira.statuses', 'jira.users', 'jira.labels',
            'jira.filters', 'jira.groups', 'jira.link_types', 'jira.transitions', 'jira.service_desks', 'jira.request_types',
        ];
    }

    protected function appName(): string
    {
        return 'Jira';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'jira.sites' => $this->sites($query),
            'jira.projects' => $this->paged($query, '/project/search', ['query' => $query->search, 'orderBy' => 'name'], 'values', fn (array $project): NodeOption => new NodeOption((string) $project['key'], (string) ($project['name'] ?? $project['key']), (string) $project['key'])),
            'jira.issue_types' => $this->issueTypes($query),
            'jira.priorities' => $this->paged($query, '/priority/search', [], 'values', fn (array $priority): NodeOption => new NodeOption((string) $priority['name'], (string) $priority['name'], $priority['description'] ?? null)),
            'jira.statuses' => $this->statuses($query),
            'jira.users' => $this->users($query),
            'jira.labels' => $this->labels($query),
            'jira.filters' => $this->paged($query, '/filter/search', ['filterName' => $query->search, 'expand' => 'jql'], 'values', fn (array $filter): NodeOption => new NodeOption((string) $filter['id'], (string) ($filter['name'] ?? $filter['id']), $filter['jql'] ?? null)),
            'jira.groups' => $this->paged($query, '/group/bulk', ['query' => $query->search], 'values', fn (array $group): NodeOption => new NodeOption((string) $group['name'], (string) $group['name'])),
            'jira.link_types' => $this->linkTypes($query),
            'jira.transitions' => $this->transitions($query),
            'jira.service_desks' => $this->serviceDesks($query),
            default => $this->requestTypes($query),
        };
    }

    private function sites(NodeOptionsQuery $query): NodeOptionsPage
    {
        $options = collect($this->sitesOf($query))
            ->filter(fn (array $site): bool => $query->matches($site['name'], $site['url']))
            ->map(fn (array $site): NodeOption => new NodeOption($site['id'], $site['name'] !== '' ? $site['name'] : $site['url'], $site['url']))
            ->values();

        return new NodeOptionsPage($options);
    }

    private function issueTypes(NodeOptionsQuery $query): NodeOptionsPage
    {
        $project = $query->configString('project');

        if ($project === null || str_contains($project, '{{')) {
            $types = $this->getJson($query, $this->base($query).'/issuetype');
        } else {
            $types = (array) ($this->getJson($query, $this->base($query).'/issue/createmeta/'.rawurlencode($project).'/issuetypes', ['maxResults' => 100])['issueTypes'] ?? []);
        }

        $options = collect($types)
            ->filter(fn (mixed $type): bool => is_array($type) && isset($type['name']) && $query->matches((string) $type['name']))
            ->map(fn (array $type): NodeOption => new NodeOption((string) $type['name'], (string) $type['name'], ($type['subtask'] ?? false) ? 'Sub-task' : ($type['description'] ?? null)))
            ->unique('value')
            ->values();

        return new NodeOptionsPage($options);
    }

    /**
     * The statuses of the node's project, or of the whole site.
     */
    private function statuses(NodeOptionsQuery $query): NodeOptionsPage
    {
        $project = $query->configString('project');

        $statuses = $project === null || str_contains($project, '{{')
            ? $this->getJson($query, $this->base($query).'/status')
            : collect((array) $this->getJson($query, $this->base($query).'/project/'.rawurlencode($project).'/statuses'))->flatMap(fn (mixed $type): array => (array) ($type['statuses'] ?? []))->all();

        $options = collect($statuses)
            ->filter(fn (mixed $status): bool => is_array($status) && isset($status['name']) && $query->matches((string) $status['name']))
            ->map(fn (array $status): NodeOption => new NodeOption((string) $status['name'], (string) $status['name'], $status['statusCategory']['name'] ?? null))
            ->unique('value')
            ->values();

        return new NodeOptionsPage($options);
    }

    private function users(NodeOptionsQuery $query): NodeOptionsPage
    {
        $users = $query->search !== null
            ? $this->getJson($query, $this->base($query).'/user/search', ['query' => $query->search, 'maxResults' => self::PAGE_SIZE])
            : $this->getJson($query, $this->base($query).'/users/search', ['maxResults' => self::PAGE_SIZE, 'startAt' => $this->intCursor($query, 0)]);

        $options = collect($users)
            ->filter(fn (mixed $user): bool => is_array($user) && isset($user['accountId']) && ($user['accountType'] ?? 'atlassian') === 'atlassian' && ($user['active'] ?? true) === true)
            ->map(fn (array $user): NodeOption => new NodeOption((string) $user['accountId'], (string) ($user['displayName'] ?? $user['accountId']), $user['emailAddress'] ?? null))
            ->values();

        $next = $query->search === null && count($users) >= self::PAGE_SIZE ? (string) ($this->intCursor($query, 0) + count($users)) : null;

        return new NodeOptionsPage($options, $next);
    }

    private function labels(NodeOptionsQuery $query): NodeOptionsPage
    {
        $start = $this->intCursor($query, 0);
        $body = $this->getJson($query, $this->base($query).'/label', ['startAt' => $start, 'maxResults' => self::PAGE_SIZE]);

        $options = collect((array) ($body['values'] ?? []))
            ->filter(fn (mixed $label): bool => is_string($label) && $query->matches($label))
            ->map(fn (string $label): NodeOption => new NodeOption($label))
            ->values();

        return new NodeOptionsPage($options, ($body['isLast'] ?? true) === false ? (string) ($start + count((array) ($body['values'] ?? []))) : null);
    }

    private function linkTypes(NodeOptionsQuery $query): NodeOptionsPage
    {
        $options = collect((array) ($this->getJson($query, $this->base($query).'/issueLinkType')['issueLinkTypes'] ?? []))
            ->filter(fn (mixed $type): bool => is_array($type) && isset($type['name']) && $query->matches((string) $type['name']))
            ->map(fn (array $type): NodeOption => new NodeOption((string) $type['name'], (string) $type['name'], trim(($type['outward'] ?? '').' / '.($type['inward'] ?? ''), ' /')))
            ->values();

        return new NodeOptionsPage($options);
    }

    /**
     * The moves open to the node's `issue` right now.
     *
     * @throws ValidationException
     */
    private function transitions(NodeOptionsQuery $query): NodeOptionsPage
    {
        $issue = trim((string) $query->configString('issue'));

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*-\d+$/', $issue) !== 1) {
            throw ValidationException::withMessages(['config.issue' => 'Enter the issue key first, e.g. APP-123.']);
        }

        $options = collect((array) ($this->getJson($query, $this->base($query).'/issue/'.rawurlencode($issue).'/transitions')['transitions'] ?? []))
            ->filter(fn (mixed $transition): bool => is_array($transition) && isset($transition['name']) && $query->matches((string) $transition['name'], (string) ($transition['to']['name'] ?? '')))
            ->map(fn (array $transition): NodeOption => new NodeOption((string) $transition['name'], (string) $transition['name'], isset($transition['to']['name']) ? 'To '.$transition['to']['name'] : null))
            ->values();

        return new NodeOptionsPage($options);
    }

    private function serviceDesks(NodeOptionsQuery $query): NodeOptionsPage
    {
        $start = $this->intCursor($query, 0);
        $body = $this->getJson($query, $this->desk($query).'/servicedesk', ['start' => $start, 'limit' => self::PAGE_SIZE]);

        $options = collect((array) ($body['values'] ?? []))
            ->filter(fn (mixed $desk): bool => is_array($desk) && isset($desk['id']) && $query->matches((string) ($desk['projectName'] ?? '')))
            ->map(fn (array $desk): NodeOption => new NodeOption((string) $desk['id'], (string) ($desk['projectName'] ?? $desk['id']), $desk['projectKey'] ?? null))
            ->values();

        return new NodeOptionsPage($options, ($body['isLastPage'] ?? true) === false ? (string) ($start + count((array) ($body['values'] ?? []))) : null);
    }

    /**
     * @throws ValidationException
     */
    private function requestTypes(NodeOptionsQuery $query): NodeOptionsPage
    {
        $desk = trim((string) $query->configString('service_desk'));

        if (preg_match('/^\d+$/', $desk) !== 1) {
            throw ValidationException::withMessages(['config.service_desk' => 'Pick a service desk first.']);
        }

        $start = $this->intCursor($query, 0);
        $body = $this->getJson($query, $this->desk($query)."/servicedesk/{$desk}/requesttype", ['start' => $start, 'limit' => self::PAGE_SIZE]);

        $options = collect((array) ($body['values'] ?? []))
            ->filter(fn (mixed $type): bool => is_array($type) && isset($type['id']) && $query->matches((string) ($type['name'] ?? '')))
            ->map(fn (array $type): NodeOption => new NodeOption((string) $type['id'], (string) ($type['name'] ?? $type['id']), $type['description'] ?? null))
            ->values();

        return new NodeOptionsPage($options, ($body['isLastPage'] ?? true) === false ? (string) ($start + count((array) ($body['values'] ?? []))) : null);
    }

    /**
     * A list that pages by `startAt` and says `isLast`, as Jira's newer lists do.
     *
     * @param  array<string, mixed>  $params
     * @param  callable(array<string, mixed>): NodeOption  $option
     */
    private function paged(NodeOptionsQuery $query, string $path, array $params, string $key, callable $option): NodeOptionsPage
    {
        $start = $this->intCursor($query, 0);
        $body = $this->getJson($query, $this->base($query).$path, [...$params, 'startAt' => $start, 'maxResults' => self::PAGE_SIZE]);

        $options = collect((array) ($body[$key] ?? []))
            ->filter(fn (mixed $record): bool => is_array($record))
            ->map(fn (array $record): NodeOption => $option($record))
            ->values();

        return new NodeOptionsPage($options, ($body['isLast'] ?? true) === false ? (string) ($start + count((array) ($body[$key] ?? []))) : null);
    }

    /**
     * Jira's REST API for the node's site.
     *
     * @throws ConnectorException
     */
    private function base(NodeOptionsQuery $query): string
    {
        return self::GATEWAY."/ex/jira/{$this->site($query)['id']}/rest/api/3";
    }

    /**
     * Service Management's API for the node's site.
     *
     * @throws ConnectorException
     */
    private function desk(NodeOptionsQuery $query): string
    {
        return self::GATEWAY."/ex/jira/{$this->site($query)['id']}/rest/servicedeskapi";
    }

    /**
     * The site the node's `site` field names (an id, an address or a name), or
     * the account's first.
     *
     * @return array{id: string, url: string, name: string}
     *
     * @throws ConnectorException
     */
    private function site(NodeOptionsQuery $query): array
    {
        $sites = $this->sitesOf($query);
        $wanted = strtolower(rtrim(trim((string) $query->configString('site')), '/'));

        if ($sites === []) {
            throw new ConnectorException('This Atlassian account has no Jira site.');
        }

        if ($wanted === '' || str_contains($wanted, '{{')) {
            return $sites[0];
        }

        foreach ($sites as $site) {
            if (in_array($wanted, [strtolower($site['id']), strtolower($site['url']), strtolower($site['name']), strtolower(preg_replace('~^https?://~', '', $site['url']) ?? '')], true)) {
                return $site;
            }
        }

        throw new ConnectorException("The connected account can't open a Jira site called \"{$wanted}\".");
    }

    /**
     * Every site the account reaches. Kept briefly, as a dropdown asks for
     * several lists in a row.
     *
     * @return list<array{id: string, url: string, name: string}>
     *
     * @throws ConnectorException
     */
    private function sitesOf(NodeOptionsQuery $query): array
    {
        return Cache::remember('jira:sites:'.sha1((string) $query->token), 300, function () use ($query): array {
            $resources = $this->getJson($query, self::GATEWAY.'/oauth/token/accessible-resources');

            return array_values(array_filter(
                array_map(fn (mixed $site): array => ['id' => (string) ($site['id'] ?? ''), 'url' => rtrim((string) ($site['url'] ?? ''), '/'), 'name' => (string) ($site['name'] ?? '')], $resources),
                fn (array $site): bool => $site['id'] !== '',
            ));
        });
    }
}
