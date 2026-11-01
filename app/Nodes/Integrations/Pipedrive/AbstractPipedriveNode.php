<?php

namespace App\Nodes\Integrations\Pipedrive;

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
 * Shared request, error handling, validation and result shaping for the Pipedrive
 * node family, on API v1. The API token goes in an `x-api-token` header. A reply is
 * `{success, data, additional_data}`; lists page with `start` and `limit` (at most
 * 500) and say whether more is left. Ids are numbers, except a lead's, which is a
 * UUID. Custom fields are named by a long generated key, so they travel as a JSON
 * map of key and value.
 */
abstract class AbstractPipedriveNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.pipedrive.com/v1';

    public function category(): string
    {
        return 'pipedrive';
    }

    /**
     * Call Pipedrive and return the whole decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped; booleans are sent as 1 and 0
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function pipedrive(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->pipedriveResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Pipedrive and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function pipedriveResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $options = ['query' => array_map(fn (mixed $value): mixed => is_bool($value) ? (int) $value : $value, array_filter($query, fn (mixed $value): bool => $value !== null))];

        if ($body !== null) {
            $options['json'] = (object) array_filter($body, fn (mixed $value): bool => $value !== null);
        }

        return Http::withHeaders(['x-api-token' => $this->resolveAccessToken($run, $config)])->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful() && $response->json('success') !== false) {
            return;
        }

        $message = (string) ($response->json('error') ?? "HTTP {$response->status()}");
        $info = $response->json('error_info');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Pipedrive: the API token was refused.',
            $response->status() === 403 => ' This Pipedrive user is not allowed to do that.',
            $response->status() === 429 => ' Pipedrive is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Pipedrive error: '.mb_substr($message, 0, 300).(str_ends_with($message, '.') ? '' : '.').(is_string($info) && $info !== '' ? ' '.mb_substr($info, 0, 200) : '').$hint);
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
        $start = 0;

        do {
            $page = $this->pipedrive($run, $config, 'GET', $path, [...$query, 'start' => $start, 'limit' => min(500, $limit - count($records))]);
            array_push($records, ...array_values(array_filter((array) ($page['data'] ?? []), 'is_array')));
            $more = (bool) data_get($page, 'additional_data.pagination.more_items_in_collection', false);
            $start = (int) data_get($page, 'additional_data.pagination.next_start', 0);
        } while ($more && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * An id (a number) from a setting, checked so it can sit in a URL.
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

        return preg_match('/^\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Pipedrive id (a number).");
    }

    /**
     * A lead id (a UUID) from a setting, checked.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function leadId(array $config, string $key = 'record_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The lead is required.');
        }

        return preg_match('/^[0-9a-f-]{36}$/i', $value) === 1 ? $value : throw new InvalidArgumentException('The lead must be a Pipedrive lead id (a UUID).');
    }

    /**
     * An optional id: its number, or null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?int
    {
        return filled($config[$key] ?? null) ? (int) $this->numericId($config, $key, $label) : null;
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
     * Email addresses or phone numbers as Pipedrive's list of `{value, primary}` entries; null when empty.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{value: string, primary: bool}>|null
     *
     * @throws InvalidArgumentException
     */
    protected function contactList(array $config, string $key, bool $emails): ?array
    {
        $values = is_array($config[$key] ?? null) ? $config[$key] : preg_split('/[\s,;]+/', (string) ($config[$key] ?? ''));
        $values = array_values(array_unique(array_filter(array_map(fn (mixed $value): string => $emails ? strtolower(trim((string) $value)) : trim((string) $value), (array) $values), fn (string $value): bool => $value !== '')));

        foreach ($values as $value) {
            if ($emails && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("\"{$value}\" is not an email address.");
            }
        }

        $entries = [];

        foreach (array_slice($values, 0, 5) as $index => $value) {
            $entries[] = ['value' => $value, 'primary' => $index === 0];
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * The value setting as a number; null when empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function amount(array $config): int|float|null
    {
        $value = $config['value'] ?? null;

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : throw new InvalidArgumentException('The value must be a number.');
    }

    /**
     * A time of day or a duration as `HH:MM`; null when empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function clock(array $config, string $key): ?string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{1,2}:\d{2}$/', $value) === 1 ? $value : throw new InvalidArgumentException('Times are written HH:MM, like 14:30.');
    }

    /**
     * The custom fields of a record, from a JSON map of generated key and value.
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
            throw new InvalidArgumentException('The custom fields must be a JSON object, e.g. {"a1b2c3d4": "Gold"}.');
        }

        foreach (array_keys($fields) as $key) {
            if (preg_match('/^[A-Za-z0-9_]+$/', (string) $key) !== 1) {
                throw new InvalidArgumentException("\"{$key}\" is not a Pipedrive field key.");
            }
        }

        return $fields;
    }

    /**
     * A day as Pipedrive wants it: `YYYY-MM-DD`; null when empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function day(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->toDateString() : null;
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
     * A record as a flat map: its fields as Pipedrive names them, plus its id first and the names behind linked ids.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function shapeRecord(array $record): array
    {
        $flat = [];

        foreach ($record as $key => $value) {
            $flat[$key] = match (true) {
                is_array($value) && isset($value['value'], $value['name']) => $value['name'],
                is_array($value) && isset($value['value']) && ! array_is_list($value) => $value['value'],
                default => $value,
            };

            if (is_array($value) && isset($value['value']) && ! array_is_list($value) && in_array($key, ['person_id', 'org_id', 'user_id', 'creator_user_id', 'owner_id'], true)) {
                $flat[$key.'_value'] = $value['value'];
            }
        }

        return ['id' => $record['id'] ?? null, 'fields' => (object) $flat, 'created_at' => $record['add_time'] ?? null, 'updated_at' => $record['update_time'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function customFieldsField(): array
    {
        return Field::advanced(Field::json('Custom fields (JSON)', 'By the field\'s key from Pipedrive > Settings > Data fields, e.g. {"a1b2c3d4e5": "Gold"}.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function userField(string $title = 'Owner'): array
    {
        return Field::advanced(Field::dynamic($title, 'pipedrive.users', 'Empty uses the connected user.', 'Ada Lovelace'));
    }
}
