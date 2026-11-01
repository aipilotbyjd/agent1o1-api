<?php

namespace App\Nodes\Integrations\ClickUp;

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
 * Shared request, error handling, validation and result shaping for the ClickUp
 * node family, on API v2. The personal token goes in the `Authorization` header
 * as it is (no "Bearer"). Times are milliseconds since 1970, sent and returned as
 * text. Work is arranged workspace (called a team in the API) > space > folder >
 * list > task; a list may sit straight in a space.
 */
abstract class AbstractClickUpNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.clickup.com/api/v2';

    public function category(): string
    {
        return 'clickup';
    }

    /**
     * Call ClickUp and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped; a list value is sent once per item as `name[]`
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function clickup(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->clickupResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call ClickUp and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function clickupResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $pairs = [];

        foreach (array_filter($query, fn (mixed $value): bool => $value !== null) as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $pairs[] = rawurlencode(is_array($value) ? "{$name}[]" : (string) $name).'='.rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
            }
        }

        $options = $body === null ? [] : ['json' => (object) array_filter($body, fn (mixed $value): bool => $value !== null)];

        return Http::withHeaders(['Authorization' => $this->resolveAccessToken($run, $config)])->acceptJson()->timeout(30)
            ->send(strtoupper($method), self::API_URL.$path.($pairs === [] ? '' : '?'.implode('&', $pairs)), $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('err') ?? $response->json('error') ?? "HTTP {$response->status()}");
        $code = $response->json('ECODE');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect ClickUp: the token was refused.',
            $response->status() === 429 => ' ClickUp is rate limiting the account (100 requests a minute); try again in a moment.',
            default => '',
        };

        throw new RuntimeException("ClickUp error: {$message}".($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * A numeric id (workspace, space, folder, list) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required: pick one from the list.");
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a ClickUp id (a number): pick one from the list.");
    }

    /**
     * A task id (letters and digits) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function taskId(array $config, string $key = 'task_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The task is required.');
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1 ? $value : throw new InvalidArgumentException('The task is not a ClickUp task id: copy it from Create Task or List Tasks.');
    }

    /**
     * A list of numeric ids from a list or comma-separated text.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function numericIds(mixed $value, string $label): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);

        return array_values(array_unique(array_map(fn (mixed $part): string => $this->numericId(['id' => $part], 'id', $label), array_filter((array) $parts, fn (mixed $part): bool => trim((string) $part) !== ''))));
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
     * A date setting as ClickUp wants it: milliseconds since 1970 (as a number); null when empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function millis(array $config, string $key): ?int
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->getTimestampMs() : null;
    }

    /**
     * Milliseconds since 1970 as an ISO date; null when empty.
     */
    protected function isoFromMillis(mixed $value): ?string
    {
        return is_numeric($value) && (int) $value > 0 ? Carbon::createFromTimestampMsUTC((int) $value)->toIso8601String() : null;
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
     * The priority setting as ClickUp's number: 1 urgent to 4 low; null for none or unset.
     *
     * @param  array<string, mixed>  $config
     */
    protected function priority(array $config): ?int
    {
        return match ($config['priority'] ?? null) {
            'urgent' => 1,
            'high' => 2,
            'normal' => 3,
            'low' => 4,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function shapeTask(array $task): array
    {
        return [
            'id' => $task['id'] ?? null,
            'custom_id' => $task['custom_id'] ?? null,
            'name' => $task['name'] ?? null,
            'description' => $task['text_content'] ?? $task['description'] ?? null,
            'status' => data_get($task, 'status.status'),
            'priority' => data_get($task, 'priority.priority'),
            'assignees' => array_values(array_filter(array_map(fn (mixed $user): ?string => is_array($user) ? ($user['username'] ?? $user['email'] ?? null) : null, (array) ($task['assignees'] ?? [])))),
            'assignee_ids' => array_values(array_filter(array_map(fn (mixed $user): ?string => is_array($user) && isset($user['id']) ? (string) $user['id'] : null, (array) ($task['assignees'] ?? [])))),
            'tags' => array_values(array_filter(array_map(fn (mixed $tag): ?string => is_array($tag) ? ($tag['name'] ?? null) : null, (array) ($task['tags'] ?? [])))),
            'due_at' => $this->isoFromMillis($task['due_date'] ?? null),
            'start_at' => $this->isoFromMillis($task['start_date'] ?? null),
            'created_at' => $this->isoFromMillis($task['date_created'] ?? null),
            'updated_at' => $this->isoFromMillis($task['date_updated'] ?? null),
            'updated_ms' => isset($task['date_updated']) ? (string) $task['date_updated'] : null,
            'closed_at' => $this->isoFromMillis($task['date_closed'] ?? null),
            'url' => $task['url'] ?? null,
            'list_id' => data_get($task, 'list.id'),
            'list' => data_get($task, 'list.name'),
            'space_id' => data_get($task, 'space.id'),
            'parent_id' => $task['parent'] ?? null,
            'creator' => data_get($task, 'creator.username'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function workspaceField(): array
    {
        return Field::dynamic('Workspace', 'clickup.workspaces', 'Pick a workspace.', 'Acme');
    }

    /**
     * The space, folder and list pickers, each narrowing the next.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function locationFields(bool $listRequired = true): array
    {
        return [
            'space_id' => Field::dynamic('Space', 'clickup.spaces', null, 'Marketing', dependsOn: ['workspace_id']),
            'folder_id' => Field::advanced(Field::dynamic('Folder', 'clickup.folders', 'Leave empty for a list that sits straight in the space.', 'Campaigns', dependsOn: ['space_id'])),
            'list_id' => Field::dynamic('List', 'clickup.lists', $listRequired ? 'Pick a list.' : null, 'Backlog', dependsOn: ['space_id', 'folder_id']),
        ];
    }
}
