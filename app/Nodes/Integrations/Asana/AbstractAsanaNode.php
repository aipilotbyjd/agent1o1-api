<?php

namespace App\Nodes\Integrations\Asana;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Asana
 * node family. The personal access token goes in a bearer header; Asana wraps a
 * request body and a reply in `data`, names fields by gid (a number kept as
 * text), only returns the fields asked for (`opt_fields`), and pages with
 * `next_page.offset`.
 */
abstract class AbstractAsanaNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://app.asana.com/api/1.0';

    /**
     * The task fields every task node asks for.
     */
    protected const string TASK_FIELDS = 'name,notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,permalink_url,assignee.name,assignee.email,parent.name,projects.name,memberships.section.name,tags.name,num_subtasks';

    public function category(): string
    {
        return 'asana';
    }

    /**
     * Call Asana and return the whole decoded body (`data`, and `next_page` when there is one).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped; booleans are sent as true and false
     * @param  array<string, mixed>|null  $data  the request body, wrapped in `data` here; null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function asana(Run $run, array $config, string $method, string $path, array $query = [], ?array $data = null): array
    {
        $response = $this->asanaResponse($run, $config, $method, $path, $query, $data);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Asana and return the response as it is.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $data
     */
    protected function asanaResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $data = null): Response
    {
        $options = ['query' => array_map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, array_filter($query, fn (mixed $value): bool => $value !== null))];

        if ($data !== null) {
            $options['json'] = ['data' => (object) array_filter($data, fn (mixed $value): bool => $value !== null)];
        }

        return Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('errors.0.message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Asana: the token was refused.',
            $response->status() === 402 => ' This needs a paid Asana plan.',
            $response->status() === 429 => ' Asana is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Asana error: {$message}".(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $records = [];
        $offset = null;

        do {
            $page = $this->asana($run, $config, 'GET', $path, [...$query, 'limit' => min(100, $limit - count($records)), 'offset' => $offset]);
            array_push($records, ...array_values(array_filter((array) ($page['data'] ?? []), 'is_array')));
            $offset = data_get($page, 'next_page.offset');
        } while ($offset !== null && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * A gid from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function gid(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be an Asana id (a number): pick one from the list.");
    }

    /**
     * The workspace a node works in: its setting, else the account's first.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function workspace(Run $run, array $config): string
    {
        if (filled($config['workspace_id'] ?? null)) {
            return $this->gid($config, 'workspace_id', 'The workspace');
        }

        $gid = data_get($this->asana($run, $config, 'GET', '/users/me', ['opt_fields' => 'workspaces.gid']), 'data.workspaces.0.gid');

        return $gid !== null ? (string) $gid : throw new RuntimeException('This Asana account has no workspace.');
    }

    /**
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
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A date setting as Asana wants a day: `YYYY-MM-DD`; null when empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function day(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->toDateString() : null;
    }

    /**
     * Who a task is assigned to: `me`, an email address or a user gid.
     *
     * @throws InvalidArgumentException
     */
    protected function person(mixed $value): string
    {
        $person = trim((string) $value);

        return $person === 'me' || preg_match('/^\d+$/', $person) === 1 || filter_var($person, FILTER_VALIDATE_EMAIL) !== false
            ? $person
            : throw new InvalidArgumentException('Assign to "me", an email address or an Asana user id.');
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function shapeTask(array $task): array
    {
        return [
            'id' => $task['gid'] ?? null,
            'name' => $task['name'] ?? null,
            'notes' => $task['notes'] ?? null,
            'completed' => (bool) ($task['completed'] ?? false),
            'completed_at' => $task['completed_at'] ?? null,
            'created_at' => $task['created_at'] ?? null,
            'modified_at' => $task['modified_at'] ?? null,
            'due_on' => $task['due_on'] ?? null,
            'due_at' => $task['due_at'] ?? null,
            'start_on' => $task['start_on'] ?? null,
            'url' => $task['permalink_url'] ?? null,
            'assignee' => data_get($task, 'assignee.name'),
            'assignee_email' => data_get($task, 'assignee.email'),
            'assignee_id' => data_get($task, 'assignee.gid'),
            'parent_id' => data_get($task, 'parent.gid'),
            'parent_name' => data_get($task, 'parent.name'),
            'projects' => array_values(array_filter(array_map(fn (mixed $project): ?string => is_array($project) ? ($project['name'] ?? null) : null, (array) ($task['projects'] ?? [])))),
            'project_ids' => array_values(array_filter(array_map(fn (mixed $project): ?string => is_array($project) ? ($project['gid'] ?? null) : null, (array) ($task['projects'] ?? [])))),
            'sections' => array_values(array_filter(array_map(fn (mixed $membership): ?string => is_array($membership) ? data_get($membership, 'section.name') : null, (array) ($task['memberships'] ?? [])))),
            'tags' => array_values(array_filter(array_map(fn (mixed $tag): ?string => is_array($tag) ? ($tag['name'] ?? null) : null, (array) ($task['tags'] ?? [])))),
            'subtask_count' => $task['num_subtasks'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function workspaceField(): array
    {
        return Field::advanced(Field::dynamic('Workspace', 'asana.workspaces', 'Empty uses the account\'s first workspace.', 'Acme'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectField(string $title = 'Project'): array
    {
        return Field::dynamic($title, 'asana.projects', 'Pick a project.', 'Website launch', dependsOn: ['workspace_id']);
    }
}
