<?php

namespace App\Nodes\Integrations\Airtable;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Airtable
 * node family. The personal access token goes in a bearer header. A base is
 * `app…`, a table `tbl…` (or its name), a record `rec…`; lists page with an
 * `offset` token, ten records can be written at a time, and a field is named by
 * its title.
 */
abstract class AbstractAirtableNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.airtable.com/v0';

    public function category(): string
    {
        return 'airtable';
    }

    /**
     * Call Airtable and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped; a list value is sent once per item as `name[]`
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function airtable(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->airtableResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Airtable and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function airtableResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $pairs = [];

        foreach (array_filter($query, fn (mixed $value): bool => $value !== null) as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $pairs[] = (is_array($value) ? rawurlencode("{$name}[]") : rawurlencode((string) $name)).'='.rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
            }
        }

        $options = $body === null ? [] : ['json' => $body];

        return Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30)
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

        $error = $response->json('error');
        $message = is_array($error) ? (string) ($error['message'] ?? $error['type'] ?? "HTTP {$response->status()}") : (string) ($error ?? "HTTP {$response->status()}");
        $type = is_array($error) ? ($error['type'] ?? null) : null;
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Airtable: the token was refused.',
            $response->status() === 403 => ' The token does not have access to this base or this scope: edit it at airtable.com/create/tokens.',
            $response->status() === 429 => ' Airtable allows 5 requests a second per base; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Airtable error: {$message}".($type ? " ({$type})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read records page by page until `$limit` are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listRecords(Run $run, array $config, array $query, int $limit): array
    {
        $records = [];
        $offset = null;

        do {
            $page = $this->airtable($run, $config, 'GET', $this->tablePath($config), [...$query, 'pageSize' => min(100, $limit - count($records)), 'offset' => $offset]);
            array_push($records, ...array_values(array_filter((array) ($page['records'] ?? []), 'is_array')));
            $offset = $page['offset'] ?? null;
        } while ($offset !== null && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * `/{base}/{table}` for the step.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function tablePath(array $config): string
    {
        $table = trim((string) ($config['table'] ?? ''));

        if ($table === '') {
            throw new InvalidArgumentException('The table is required: pick one from the list.');
        }

        return '/'.$this->baseId($config).'/'.rawurlencode($table);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function baseId(array $config): string
    {
        $base = trim((string) ($config['base_id'] ?? ''));

        if ($base === '') {
            throw new InvalidArgumentException('The base is required: pick one from the list.');
        }

        return preg_match('/^app[A-Za-z0-9]+$/', $base) === 1 ? $base : throw new InvalidArgumentException('The base is not an Airtable base id (it starts with app): pick one from the list.');
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function recordId(array $config, string $key = 'record_id'): string
    {
        $record = trim((string) ($config[$key] ?? ''));

        if ($record === '') {
            throw new InvalidArgumentException('The record is required.');
        }

        return preg_match('/^rec[A-Za-z0-9]+$/', $record) === 1 ? $record : throw new InvalidArgumentException('The record is not an Airtable record id (it starts with rec): copy it from a previous step.');
    }

    /**
     * The fields to write, from a JSON setting that may arrive as text.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function fields(array $config, string $key = 'fields'): array
    {
        $value = $config[$key] ?? null;
        $fields = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($fields) || $fields === [] || array_is_list($fields)) {
            throw new InvalidArgumentException('Give the fields as a JSON object, e.g. {"Name": "Ada", "Status": "Todo"}.');
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredText(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A value placed in a formula as text: quoted, with quotes and backslashes escaped.
     */
    protected function formulaText(string $value): string
    {
        return "'".str_replace(["\\", "'", "\r", "\n"], ['\\\\', "\\'", ' ', ' '], $value)."'";
    }

    /**
     * A field name placed in a formula: `{Name}`.
     *
     * @throws InvalidArgumentException
     */
    protected function formulaField(string $name): string
    {
        $name = trim($name);

        return $name !== '' && ! str_contains($name, '}') ? '{'.$name.'}' : throw new InvalidArgumentException('Give the name of the field, without braces.');
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
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function shapeRecord(array $record): array
    {
        return ['id' => $record['id'] ?? null, 'created_at' => $record['createdTime'] ?? null, 'fields' => (object) ($record['fields'] ?? [])];
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseField(): array
    {
        return Field::dynamic('Base', 'airtable.bases', 'Pick a base.', 'Marketing');
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableField(): array
    {
        return Field::dynamic('Table', 'airtable.tables', 'Pick a table.', 'Campaigns', dependsOn: ['base_id']);
    }
}
