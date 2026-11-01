<?php

namespace App\Nodes\Integrations\ZohoProjects;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Shared request, error handling, validation and result shaping for the Zoho
 * Projects node family. Projects shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called on the v3 API under `/portal/{portal id}`, with
 * the portal id taken from the connection. A list pages with `index` (where to
 * start, from 1) and `range` (at most 200) and answers its records under a key;
 * an empty list may be a 204. Ids are long numbers.
 */
abstract class AbstractZohoProjectsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_projects';
    }

    /**
     * Call Zoho Projects and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function projects(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->projectsResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Zoho Projects and return the response as it is, for a node that treats a 404 as an answer.
     * The path starts after the portal, e.g. `/projects`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function projectsResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $data = $this->resolveCredentialData($run, $config);
        $portal = trim((string) ($data['portal_id'] ?? ''));

        if (preg_match('/^\d+$/', $portal) !== 1) {
            throw new InvalidArgumentException('Add the Zoho Projects portal id (a number) to the connection.');
        }

        $login = app(CrmAccessTokens::class)->zohoApp($data, 'projects');
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30)->send(strtoupper($method), $login['base']."/portal/{$portal}".$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('error.message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Projects: the token was refused or lacks the Projects scopes.',
            $response->status() === 403 => ' The Zoho Projects user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Projects is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Projects error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit): array
    {
        $records = [];

        do {
            $want = min(200, $limit - count($records));
            $response = $this->projectsResponse($run, $config, 'GET', $path, [...$query, 'index' => count($records) + 1, 'range' => $want]);

            if ($response->status() !== 204) {
                $this->assertOk($response);
            }

            $rows = array_values(array_filter((array) ($response->json($key) ?? []), 'is_array'));
            array_push($records, ...$rows);
        } while (count($rows) === $want && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * An id (a long number) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d{6,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zoho Projects id (a long number).");
    }

    /**
     * An optional id: itself, or null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->numericId($config, $key, $label) : null;
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
     * A checked email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function email(array $config, string $key = 'email'): string
    {
        $email = strtolower(trim((string) ($config[$key] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException($email === '' ? 'The email address is required.' : "\"{$email}\" is not an email address.");
    }

    /**
     * Custom fields as a JSON object setting; an empty map when not given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function customFields(array $config): array
    {
        $value = $config['custom_fields'] ?? null;
        $fields = is_string($value) ? (trim($value) === '' ? [] : json_decode($value, true)) : (array) $value;

        if (! is_array($fields) || ($fields !== [] && array_is_list($fields))) {
            throw new InvalidArgumentException('The custom fields must be a JSON object, e.g. {"cf_plan": "Gold"}.');
        }

        return $fields;
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
     * A date as Projects wants it (`2026-03-31`); null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function date(array $config, string $key): ?string
    {
        if (! filled($config[$key] ?? null)) {
            return null;
        }

        try {
            return Carbon::parse((string) $config[$key])->format('Y-m-d');
        } catch (Throwable) {
            throw new InvalidArgumentException("The {$key} is not a date.");
        }
    }

    /**
     * A time from a record: an ISO string, or milliseconds since 1970.
     */
    protected function when(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return is_numeric($value) ? Carbon::createFromTimestampMs((int) $value) : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    protected function shapeProject(array $project): array
    {
        return ['id' => isset($project['id']) ? (string) $project['id'] : null, 'name' => $project['name'] ?? null, 'description' => $project['description'] ?? null, 'status' => data_get($project, 'status.name') ?? ($project['status'] ?? null), 'owner' => data_get($project, 'owner.name') ?? ($project['owner_name'] ?? null), 'start_date' => $project['start_date'] ?? null, 'end_date' => $project['end_date'] ?? null, 'percent_complete' => $project['percent_complete'] ?? null, 'created_at' => $project['created_time'] ?? null, 'updated_at' => $project['last_updated_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function shapeTask(array $task): array
    {
        return ['id' => isset($task['id']) ? (string) $task['id'] : null, 'name' => $task['name'] ?? null, 'description' => $task['description'] ?? null, 'status' => data_get($task, 'status.name') ?? ($task['status'] ?? null), 'priority' => $task['priority'] ?? null, 'project_id' => data_get($task, 'project.id') ?? ($task['project_id'] ?? null), 'project' => data_get($task, 'project.name'), 'task_list' => data_get($task, 'tasklist.name'), 'owners' => array_values(array_filter(array_map(fn (mixed $owner): ?string => is_array($owner) ? ($owner['name'] ?? null) : null, (array) ($task['owners_and_work'] ?? $task['owners'] ?? [])))), 'start_date' => $task['start_date'] ?? null, 'due_date' => $task['end_date'] ?? $task['due_date'] ?? null, 'percent_complete' => $task['percent_complete'] ?? null, 'created_at' => $task['created_time'] ?? null, 'updated_at' => $task['last_updated_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $milestone
     * @return array<string, mixed>
     */
    protected function shapeMilestone(array $milestone): array
    {
        return ['id' => isset($milestone['id']) ? (string) $milestone['id'] : null, 'name' => $milestone['name'] ?? null, 'status' => data_get($milestone, 'status.name') ?? ($milestone['status'] ?? null), 'start_date' => $milestone['start_date'] ?? null, 'end_date' => $milestone['end_date'] ?? null, 'owner' => data_get($milestone, 'owner.name') ?? ($milestone['owner_name'] ?? null)];
    }

    /**
     * @param  array<string, mixed>  $bug
     * @return array<string, mixed>
     */
    protected function shapeBug(array $bug): array
    {
        return ['id' => isset($bug['id']) ? (string) $bug['id'] : null, 'title' => $bug['title'] ?? $bug['name'] ?? null, 'description' => $bug['description'] ?? null, 'status' => data_get($bug, 'status.type') ?? data_get($bug, 'status.name') ?? ($bug['status'] ?? null), 'severity' => data_get($bug, 'severity.type') ?? data_get($bug, 'severity.name') ?? ($bug['severity'] ?? null), 'project_id' => data_get($bug, 'project.id') ?? ($bug['project_id'] ?? null), 'reporter' => data_get($bug, 'reporter.name') ?? ($bug['reporter_name'] ?? null), 'assignee' => data_get($bug, 'assignee.name') ?? ($bug['assignee_name'] ?? null), 'due_date' => $bug['due_date'] ?? null, 'created_at' => $bug['created_time'] ?? null, 'updated_at' => $bug['last_updated_time'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectField(): array
    {
        return Field::dynamic('Project', 'zoho_projects.projects', null, 'Website relaunch');
    }

    /**
     * @return array<string, mixed>
     */
    protected function userField(string $title = 'Assign to'): array
    {
        return Field::dynamic($title, 'zoho_projects.users', null, 'Ada Lovelace');
    }
}
