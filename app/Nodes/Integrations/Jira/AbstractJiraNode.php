<?php

namespace App\Nodes\Integrations\Jira;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, user and custom-field lookups and result
 * shaping for the Jira node family. Resolves the access token via
 * `ResolvesConnectorCredential` and speaks Jira Cloud's REST API v3 (and the
 * Jira Service Management API) through Atlassian's gateway.
 *
 * An Atlassian login can reach several Jira sites, and the gateway addresses
 * each by a cloud id, so every node has a `site` setting; left empty it uses
 * the account's first (usually only) site.
 *
 * Rich text is ADF on the wire and Markdown or plain text in a flow (JiraAdf).
 * A field that names a person takes an account id, an email or a display name.
 */
abstract class AbstractJiraNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string GATEWAY = 'https://api.atlassian.com';

    private const string SITES_URL = 'https://api.atlassian.com/oauth/token/accessible-resources';

    /**
     * The most records Jira returns in one page of most lists.
     */
    protected const int PAGE_SIZE = 100;

    /**
     * What an issue read asks for.
     */
    protected const string ISSUE_FIELDS = 'summary,description,status,priority,issuetype,assignee,reporter,labels,project,created,updated,duedate,resolution,resolutiondate,parent,components,fixVersions,environment';

    /**
     * The most bytes of a file the attachment nodes move.
     */
    protected const int MAX_FILE_BYTES = 10_485_760;

    public function category(): string
    {
        return 'jira';
    }

    /**
     * The site setting every node starts with, after the account picker.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function siteFields(): array
    {
        return [
            'site' => Field::advanced(Field::dynamic('Jira site', 'jira.sites', 'Which Jira site to use. Leave empty for the account\'s first site.', 'acme.atlassian.net')),
        ];
    }

    /**
     * Call Jira's REST API and return the decoded body (an empty array for an
     * empty answer).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  'api'|'servicedesk'  $api  Jira's own API, or Service Management's
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    protected function request(Run $run, array $config, string $method, string $path, array $query = [], mixed $body = null, string $api = 'api'): array
    {
        $site = $this->site($run, $config);
        $root = $api === 'servicedesk' ? 'rest/servicedeskapi' : 'rest/api/3';
        $url = self::GATEWAY."/ex/jira/{$site['id']}/{$root}{$path}";
        $query = array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '');

        $request = $this->http($this->resolveAccessToken($run, $config));

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $query),
            'DELETE' => $request->delete($url.($query === [] ? '' : '?'.http_build_query($query))),
            default => $request->withBody(json_encode($body ?? new \stdClass, JSON_THROW_ON_ERROR), 'application/json')
                ->send(strtoupper($method), $url.($query === [] ? '' : '?'.http_build_query($query))),
        };

        $this->assertOk($response, $path);

        return (array) ($response->json() ?? []);
    }

    protected function http(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    /**
     * Jira says what went wrong in `errorMessages` and `errors` (by field).
     *
     * @throws RuntimeException
     */
    protected function assertOk(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        $messages = array_merge(
            array_map('strval', (array) $response->json('errorMessages', [])),
            array_map(fn (mixed $message, string|int $field): string => "{$field}: {$message}", (array) $response->json('errors', []), array_keys((array) $response->json('errors', []))),
        );

        if ($messages === [] && is_string($response->json('message'))) {
            $messages[] = $response->json('message');
        }

        $detail = $messages === [] ? "HTTP {$response->status()}" : implode('; ', $messages);

        throw new RuntimeException("Jira API error [{$path}]: {$detail}");
    }

    /**
     * Read every page of a list that pages by `startAt`, until `$limit`
     * records are in or the list ends. `$key` is where the records sit in the
     * answer, or null when the answer is the list.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listPages(Run $run, array $config, string $path, array $query, ?string $key, int $limit, string $api = 'api'): array
    {
        $results = [];
        $start = 0;

        do {
            $answer = $this->request($run, $config, 'GET', $path, [...$query, 'startAt' => $start, 'maxResults' => min(self::PAGE_SIZE, $limit - count($results))], null, $api);
            $page = $key === null ? $answer : (array) ($answer[$key] ?? []);

            foreach ($page as $record) {
                if (is_array($record)) {
                    $results[] = $record;
                }
            }

            $start += count($page);
            $more = $page !== [] && count($results) < $limit && ($key === null ? count($page) >= min(self::PAGE_SIZE, $limit) : ($answer['isLast'] ?? true) === false);
        } while ($more);

        return array_slice($results, 0, $limit);
    }

    /**
     * Run a JQL search and return the issues, shaped. Uses the enhanced search
     * endpoint, which pages by token.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>|null  $fields
     * @return list<array<string, mixed>>
     */
    protected function searchIssues(Run $run, array $config, string $jql, int $limit, ?array $fields = null): array
    {
        $site = $this->site($run, $config);
        $issues = [];
        $token = null;

        do {
            $answer = $this->request($run, $config, 'POST', '/search/jql', [], array_filter([
                'jql' => $jql,
                'maxResults' => min(self::PAGE_SIZE, $limit - count($issues)),
                'fields' => $fields ?? explode(',', self::ISSUE_FIELDS),
                'nextPageToken' => $token,
            ], fn (mixed $value): bool => $value !== null));

            foreach ((array) ($answer['issues'] ?? []) as $issue) {
                if (is_array($issue)) {
                    $issues[] = $this->shapeIssue($issue, $site['url']);
                }
            }

            $token = is_string($answer['nextPageToken'] ?? null) && ($answer['isLast'] ?? false) !== true ? $answer['nextPageToken'] : null;
        } while ($token !== null && count($issues) < $limit);

        return array_slice($issues, 0, $limit);
    }

    /**
     * The Jira site to talk to: its cloud id, address and name.
     *
     * The setting may be a cloud id, the site's address or its name. Empty, it
     * is the account's first site. The list of sites is kept for a few minutes,
     * since every call of a flow would otherwise ask for it again.
     *
     * @param  array<string, mixed>  $config
     * @return array{id: string, url: string, name: string}
     *
     * @throws RuntimeException
     */
    protected function site(Run $run, array $config): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $sites = Cache::remember('jira:sites:'.sha1($token), 300, function () use ($token): array {
            $response = $this->http($token)->get(self::SITES_URL);
            $this->assertOk($response, 'accessible-resources');

            return array_values(array_filter(
                array_map(fn (mixed $site): array => ['id' => (string) ($site['id'] ?? ''), 'url' => rtrim((string) ($site['url'] ?? ''), '/'), 'name' => (string) ($site['name'] ?? '')], (array) $response->json()),
                fn (array $site): bool => $site['id'] !== '',
            ));
        });

        if ($sites === []) {
            throw new RuntimeException('This Atlassian account has no Jira site. Connect an account that can open one.');
        }

        $wanted = strtolower(rtrim(trim((string) ($config['site'] ?? '')), '/'));

        if ($wanted === '') {
            return $sites[0];
        }

        foreach ($sites as $site) {
            if (in_array($wanted, [strtolower($site['id']), strtolower($site['url']), strtolower($site['name']), strtolower(preg_replace('~^https?://~', '', $site['url']) ?? '')], true)) {
                return $site;
            }
        }

        throw new InvalidArgumentException("The connected account can't open a Jira site called \"{$config['site']}\".");
    }

    /**
     * The gateway address of a path on the node's site, for the few calls that
     * are not plain JSON (file uploads and downloads).
     *
     * @param  array<string, mixed>  $config
     */
    protected function url(Run $run, array $config, string $path): string
    {
        return self::GATEWAY."/ex/jira/{$this->site($run, $config)['id']}/rest/api/3{$path}";
    }

    /**
     * One issue, shaped, with the fields a flow usually wants.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function getIssue(Run $run, array $config, string $key, ?string $fields = null): array
    {
        $issue = $this->request($run, $config, 'GET', '/issue/'.rawurlencode($key), ['fields' => $fields ?? self::ISSUE_FIELDS]);

        return $this->shapeIssue($issue, $this->site($run, $config)['url']);
    }

    /**
     * An issue type as Jira wants it in a request: by id when it is a number,
     * by name otherwise.
     *
     * @return array{id?: string, name?: string}
     */
    protected function issueTypeRef(string $value): array
    {
        return ctype_digit($value) ? ['id' => $value] : ['name' => $value];
    }

    /**
     * The fields a new issue of a type can have, keyed by field id, for turning
     * custom field text into values.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<string, mixed>>
     */
    protected function createMeta(Run $run, array $config, string $project, string $issueType): array
    {
        $typeId = $issueType;

        if (! ctype_digit($issueType)) {
            $types = (array) ($this->request($run, $config, 'GET', '/issue/createmeta/'.rawurlencode($project).'/issuetypes', ['maxResults' => 100])['issueTypes'] ?? []);
            $found = collect($types)->first(fn (mixed $type): bool => is_array($type) && strcasecmp((string) ($type['name'] ?? ''), $issueType) === 0);
            $typeId = is_array($found) ? (string) $found['id'] : throw new InvalidArgumentException("The project {$project} has no issue type \"{$issueType}\".");
        }

        $fields = (array) ($this->request($run, $config, 'GET', '/issue/createmeta/'.rawurlencode($project).'/issuetypes/'.rawurlencode($typeId), ['maxResults' => 200])['fields'] ?? []);

        $keyed = [];

        foreach ($fields as $field) {
            if (is_array($field) && isset($field['fieldId'])) {
                $keyed[(string) $field['fieldId']] = $field;
            }
        }

        return $keyed;
    }

    /**
     * The fields an existing issue can be edited with, keyed by field id.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<string, mixed>>
     */
    protected function editMeta(Run $run, array $config, string $key): array
    {
        return array_map(fn (mixed $field): array => (array) $field, (array) ($this->request($run, $config, 'GET', '/issue/'.rawurlencode($key).'/editmeta')['fields'] ?? []));
    }

    /**
     * A page of Service Management's lists, which page by `start` and `limit`
     * and say `isLastPage`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listDeskPages(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $results = [];
        $start = 0;

        do {
            $answer = $this->request($run, $config, 'GET', $path, [...$query, 'start' => $start, 'limit' => min(self::PAGE_SIZE, $limit - count($results))], null, 'servicedesk');
            $page = (array) ($answer['values'] ?? []);

            foreach ($page as $record) {
                if (is_array($record)) {
                    $results[] = $record;
                }
            }

            $start += count($page);
        } while ($page !== [] && ($answer['isLastPage'] ?? true) === false && count($results) < $limit);

        return array_slice($results, 0, $limit);
    }

    /**
     * A field value reduced to something readable: a person, option, version or
     * component by its name; rich text as text; a list as a list.
     */
    protected function simplify(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($value['type']) && $value['type'] === 'doc') {
            return JiraAdf::toText($value);
        }

        if (array_is_list($value)) {
            return array_map($this->simplify(...), $value);
        }

        return $value['displayName'] ?? $value['name'] ?? $value['value'] ?? $value['key'] ?? $value;
    }

    /**
     * A non-empty string from a config field.
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
     * An issue key (APP-123) or numeric id, safe to put in a request path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function issueKey(array $config, string $key = 'issue', string $label = 'The issue'): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*-\d+$|^\d+$/', $value) === 1
            ? $value
            : throw new InvalidArgumentException("{$label} \"{$value}\" isn't an issue key like APP-123.");
    }

    /**
     * A project key (APP) or numeric id, safe to put in a request path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function projectKey(array $config, string $key = 'project'): string
    {
        $value = $this->requiredString($config, $key, 'The project');

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$|^\d+$/', $value) === 1
            ? $value
            : throw new InvalidArgumentException("The project \"{$value}\" isn't a project key like APP.");
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
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 100): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A JQL string literal: double-quoted, with quotes and backslashes escaped.
     */
    protected function jqlString(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * The settings the issue readers share for choosing issues. One way of
     * choosing is used at a time: "Filter by" says which.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function issueFilterFields(): array
    {
        return [
            'filter_mode' => Field::select('Filter by', ['basic' => 'The fields below', 'jql' => 'A JQL query', 'saved' => 'A saved Jira filter'], 'Only one way of choosing issues is used at a time.', 'basic'),
            'project' => Field::dynamic('Project', 'jira.projects', 'Fields below: the project, by key (APP) or pick one.', 'APP'),
            'statuses' => Field::dynamic('Statuses', 'jira.statuses', 'Fields below: issues in any of these statuses.', 'In Progress', multiple: true, uses: ['project']),
            'priorities' => Field::dynamic('Priorities', 'jira.priorities', 'Fields below: issues with any of these priorities.', 'High', multiple: true),
            'issue_types' => Field::dynamic('Issue types', 'jira.issue_types', 'Fields below: issues of any of these types.', 'Bug', multiple: true, uses: ['project']),
            'labels' => Field::dynamic('Labels', 'jira.labels', 'Fields below: issues with any of these labels.', 'backend', multiple: true),
            'assignee' => Field::dynamic('Assignee', 'jira.users', 'Fields below: issues assigned to this person (an email, a name, or currentUser).', 'ada@acme.com'),
            'custom_filters' => Field::advanced(Field::keyValue('Custom fields', 'Fields below: a custom field (by name) and the value it must have.')),
            'custom_filters_match' => Field::advanced(Field::select('Custom fields must', ['AND' => 'All match', 'OR' => 'At least one matches'], default: 'AND')),
            'jql' => Field::textarea('JQL', 'JQL query: any Jira Query Language, e.g. project = APP AND status = "In Progress".', 'project = APP AND priority = High'),
            'saved_filter' => Field::dynamic('Saved filter', 'jira.filters', 'Saved filter: a filter from Jira. Changes to it in Jira apply here.', '10042'),
        ];
    }

    /**
     * The JQL (without its ORDER BY) for the way of choosing issues the node
     * is set to. Empty when it was left open: every issue the account can see.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function filterJql(Run $run, array $config): string
    {
        $mode = (string) ($config['filter_mode'] ?? 'basic');

        if ($mode === 'jql') {
            return $this->splitOrder($this->requiredString($config, 'jql', 'The JQL query'))[0];
        }

        if ($mode === 'saved') {
            $filter = $this->request($run, $config, 'GET', '/filter/'.rawurlencode($this->requiredString($config, 'saved_filter', 'The saved filter')));

            return $this->splitOrder((string) ($filter['jql'] ?? ''))[0];
        }

        $clauses = [];

        if (filled($config['project'] ?? null)) {
            $clauses[] = 'project = '.$this->jqlString($this->projectKey($config));
        }

        foreach (['statuses' => 'status', 'priorities' => 'priority', 'issue_types' => 'issuetype', 'labels' => 'labels'] as $key => $field) {
            if (($values = $this->csv($config[$key] ?? null)) !== []) {
                $clauses[] = "{$field} in (".implode(', ', array_map($this->jqlString(...), $values)).')';
            }
        }

        if (filled($config['assignee'] ?? null)) {
            $assignee = trim((string) $config['assignee']);
            $clauses[] = strcasecmp($assignee, 'currentUser') === 0 || strcasecmp($assignee, 'currentUser()') === 0
                ? 'assignee = currentUser()'
                : (in_array(strtolower($assignee), ['unassigned', 'none'], true) ? 'assignee is EMPTY' : 'assignee = '.$this->jqlString((string) $this->resolveUser($run, $config, $assignee)));
        }

        $custom = [];

        foreach ((array) ($config['custom_filters'] ?? []) as $field => $value) {
            $name = preg_match('/^customfield_(\d+)$/i', (string) $field, $id) === 1 ? "cf[{$id[1]}]" : $this->jqlString((string) $field);
            $custom[] = "{$name} = ".(is_numeric($value) ? (string) $value : $this->jqlString((string) $value));
        }

        if ($custom !== []) {
            $clauses[] = count($custom) === 1 ? $custom[0] : '('.implode(strtoupper((string) ($config['custom_filters_match'] ?? 'AND')) === 'OR' ? ' OR ' : ' AND ', $custom).')';
        }

        return implode(' AND ', $clauses);
    }

    /**
     * A JQL query split into what it selects and how it is ordered.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitOrder(string $jql): array
    {
        $parts = preg_split('/\s+ORDER\s+BY\s+/i', trim($jql), 2) ?: [trim($jql)];

        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * `$where` with another clause added and an order put on.
     */
    protected function withClause(string $where, string $clause, string $order): string
    {
        $selected = $where === '' ? $clause : (str_contains(strtoupper($where), ' OR ') ? "({$where}) AND {$clause}" : "{$where} AND {$clause}");

        return $selected.' ORDER BY '.$order;
    }

    /**
     * The `accountId` of a person from an account id, an email or a name;
     * null when none was given.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when nobody matches
     */
    protected function resolveUser(Run $run, array $config, mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Account ids are 24 hex characters, or the `5b10...`/`712020:...` forms.
        if (preg_match('/^[0-9a-f]{24}$|^\d{5,6}:[0-9a-f-]{36}$/i', $value) === 1) {
            return $value;
        }

        $users = $this->request($run, $config, 'GET', '/user/search', ['query' => $value, 'maxResults' => 25]);

        $people = array_values(array_filter($users, fn (mixed $user): bool => is_array($user) && ($user['accountType'] ?? 'atlassian') === 'atlassian' && ($user['active'] ?? true) === true));
        $exact = array_values(array_filter($people, fn (array $user): bool => strcasecmp((string) ($user['emailAddress'] ?? ''), $value) === 0 || strcasecmp((string) ($user['displayName'] ?? ''), $value) === 0));
        $found = $exact[0] ?? (count($people) === 1 ? $people[0] : null);

        return is_array($found) && isset($found['accountId'])
            ? (string) $found['accountId']
            : throw new InvalidArgumentException($people === [] ? "No Jira user matches \"{$value}\"." : "More than one Jira user matches \"{$value}\". Use an email address or the account id.");
    }

    /**
     * A person for a field that takes one: `{accountId}`, or null to clear it
     * when the text is "unassigned".
     *
     * @param  array<string, mixed>  $config
     * @return array{accountId: ?string}|null
     */
    protected function userField(Run $run, array $config, mixed $value): ?array
    {
        if (filled($value) && in_array(strtolower(trim((string) $value)), ['unassigned', 'none', '-1'], true)) {
            return ['accountId' => null];
        }

        $id = $this->resolveUser($run, $config, $value);

        return $id === null ? null : ['accountId' => $id];
    }

    /**
     * Custom fields given as text, turned into what Jira wants for each.
     *
     * `$values` maps a field id (customfield_10016) or name (Story Points) to
     * text. `$metadata` is the schema of the fields the issue can have (from
     * createmeta or editmeta), keyed by field id. A single choice is its text,
     * several are comma-separated, a parent and child choice is "Parent >
     * Child", a number is its digits, a person is an account id or email.
     *
     * @param  array<string, mixed>  $config
     * @param  array<mixed>  $values
     * @param  array<string, array<string, mixed>>  $metadata
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when a field isn't one this issue has
     */
    protected function customFields(Run $run, array $config, array $values, array $metadata): array
    {
        $byName = [];

        foreach ($metadata as $id => $field) {
            $byName[strtolower((string) ($field['name'] ?? ''))] = (string) $id;
        }

        $fields = [];

        foreach ($values as $name => $value) {
            $id = isset($metadata[(string) $name]) ? (string) $name : ($byName[strtolower((string) $name)] ?? null);

            if ($id === null) {
                throw new InvalidArgumentException("This issue has no field called \"{$name}\".");
            }

            $fields[$id] = $this->fieldValue($run, $config, (array) ($metadata[$id]['schema'] ?? []), $value);
        }

        return $fields;
    }

    /**
     * One field's value in the shape its schema asks for.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $schema
     */
    private function fieldValue(Run $run, array $config, array $schema, mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        $text = trim((string) $value);
        $type = (string) ($schema['type'] ?? 'string');
        $items = (string) ($schema['items'] ?? '');

        return match (true) {
            $type === 'number' => is_numeric($text) ? $text + 0 : throw new InvalidArgumentException("\"{$text}\" is not a number."),
            $type === 'array' && $items === 'string' => $this->csv($text),
            $type === 'array' && $items === 'user' => array_map(fn (string $user): array => ['accountId' => $this->resolveUser($run, $config, $user)], $this->csv($text)),
            $type === 'array' && in_array($items, ['component', 'version'], true) => array_map(fn (string $name): array => ['name' => $name], $this->csv($text)),
            $type === 'array' => array_map(fn (string $option): array => ['value' => $option], $this->csv($text)),
            $type === 'option-with-child' => $this->cascading($text),
            $type === 'option' => ['value' => $text],
            $type === 'user' => ['accountId' => $this->resolveUser($run, $config, $text)],
            in_array($type, ['version', 'component', 'priority'], true) => ['name' => $text],
            default => $text,
        };
    }

    /**
     * "Parent > Child" as Jira's cascading select value.
     *
     * @return array<string, mixed>
     */
    private function cascading(string $text): array
    {
        [$parent, $child] = array_pad(array_map('trim', explode('>', $text, 2)), 2, null);

        return array_filter(['value' => $parent, 'child' => $child === null || $child === '' ? null : ['value' => $child]], fn (mixed $part): bool => $part !== null);
    }

    /**
     * An issue as the steps after it see it. `$siteUrl` makes the browse link.
     *
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function shapeIssue(array $issue, string $siteUrl = ''): array
    {
        $fields = (array) ($issue['fields'] ?? []);
        $key = (string) ($issue['key'] ?? '');

        return [
            'key' => $key,
            'id' => $issue['id'] ?? null,
            'url' => $siteUrl !== '' && $key !== '' ? "{$siteUrl}/browse/{$key}" : null,
            'summary' => $fields['summary'] ?? null,
            'description' => isset($fields['description']) ? JiraAdf::toText($fields['description']) : null,
            'status' => data_get($fields, 'status.name'),
            'status_category' => data_get($fields, 'status.statusCategory.name'),
            'priority' => data_get($fields, 'priority.name'),
            'issue_type' => data_get($fields, 'issuetype.name'),
            'is_subtask' => (bool) data_get($fields, 'issuetype.subtask', false),
            'project' => data_get($fields, 'project.key'),
            'project_name' => data_get($fields, 'project.name'),
            'assignee' => data_get($fields, 'assignee.displayName'),
            'assignee_id' => data_get($fields, 'assignee.accountId'),
            'assignee_email' => data_get($fields, 'assignee.emailAddress'),
            'reporter' => data_get($fields, 'reporter.displayName'),
            'reporter_id' => data_get($fields, 'reporter.accountId'),
            'labels' => array_values(array_map('strval', (array) ($fields['labels'] ?? []))),
            'components' => array_values(array_map(fn (array $component): string => (string) ($component['name'] ?? ''), (array) ($fields['components'] ?? []))),
            'fix_versions' => array_values(array_map(fn (array $version): string => (string) ($version['name'] ?? ''), (array) ($fields['fixVersions'] ?? []))),
            'parent_key' => data_get($fields, 'parent.key'),
            'parent_summary' => data_get($fields, 'parent.fields.summary'),
            'due_date' => $fields['duedate'] ?? null,
            'resolution' => data_get($fields, 'resolution.name'),
            'resolved_at' => $fields['resolutiondate'] ?? null,
            'created_at' => $fields['created'] ?? null,
            'updated_at' => $fields['updated'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function shapeUser(array $user): array
    {
        return [
            'account_id' => $user['accountId'] ?? null,
            'display_name' => $user['displayName'] ?? null,
            'email' => $user['emailAddress'] ?? null,
            'active' => $user['active'] ?? null,
            'account_type' => $user['accountType'] ?? null,
            'time_zone' => $user['timeZone'] ?? null,
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
            'body' => JiraAdf::toText($comment['body'] ?? ''),
            'author' => data_get($comment, 'author.displayName'),
            'author_id' => data_get($comment, 'author.accountId'),
            'internal' => collect((array) ($comment['properties'] ?? []))->contains(fn (mixed $property): bool => ($property['key'] ?? null) === 'sd.public.comment' && (bool) data_get($property, 'value.internal', false)) || ($comment['jsdPublic'] ?? true) === false,
            'created_at' => $comment['created'] ?? null,
            'updated_at' => $comment['updated'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    protected function shapeAttachment(array $file): array
    {
        return [
            'id' => $file['id'] ?? null,
            'filename' => $file['filename'] ?? null,
            'size' => $file['size'] ?? null,
            'mime_type' => $file['mimeType'] ?? null,
            'author' => data_get($file, 'author.displayName'),
            'created_at' => $file['created'] ?? null,
            'content_url' => $file['content'] ?? null,
        ];
    }

    /**
     * A Service Management request.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    protected function shapeRequest(array $request): array
    {
        return [
            'key' => $request['issueKey'] ?? null,
            'id' => $request['issueId'] ?? null,
            'request_type_id' => $request['requestTypeId'] ?? null,
            'service_desk_id' => $request['serviceDeskId'] ?? null,
            'status' => data_get($request, 'currentStatus.status'),
            'reporter' => data_get($request, 'reporter.displayName'),
            'created_at' => data_get($request, 'createdDate.iso8601'),
            'url' => data_get($request, '_links.web'),
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
            'key' => $project['key'] ?? null,
            'name' => $project['name'] ?? null,
            'description' => $project['description'] ?? null,
            'type' => $project['projectTypeKey'] ?? null,
            'style' => $project['style'] ?? null,
            'lead' => data_get($project, 'lead.displayName'),
            'lead_id' => data_get($project, 'lead.accountId'),
            'url' => $project['url'] ?? null,
        ];
    }
}
