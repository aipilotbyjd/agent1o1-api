<?php

namespace App\Nodes\Integrations\Zoho;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Zoho CRM
 * node family, on API v7. The connection holds a client id and secret and a
 * refresh token; a short-lived access token is asked for with those (see
 * `CrmAccessTokens`) and sent as `Zoho-oauthtoken`, to the API root of the
 * account's data center. Records are in modules (`Leads`, `Deals`) with fields
 * named by API name (`Last_Name`); a write takes `{"data": [...]}` and answers one
 * result for each record, and a lookup field is written as `{"id": "..."}`.
 */
abstract class AbstractZohoNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * For each typed module: the fields read by default.
     *
     * @var array<string, list<string>>
     */
    protected const array MODULES = [
        'Leads' => ['id', 'First_Name', 'Last_Name', 'Company', 'Email', 'Phone', 'Lead_Status', 'Lead_Source', 'Owner', 'Created_Time', 'Modified_Time'],
        'Contacts' => ['id', 'First_Name', 'Last_Name', 'Email', 'Phone', 'Title', 'Account_Name', 'Owner', 'Created_Time', 'Modified_Time'],
        'Accounts' => ['id', 'Account_Name', 'Website', 'Phone', 'Industry', 'Owner', 'Created_Time', 'Modified_Time'],
        'Deals' => ['id', 'Deal_Name', 'Amount', 'Stage', 'Closing_Date', 'Account_Name', 'Owner', 'Created_Time', 'Modified_Time'],
        'Tasks' => ['id', 'Subject', 'Status', 'Priority', 'Due_Date', 'Owner', 'Created_Time', 'Modified_Time'],
    ];

    public function category(): string
    {
        return 'zoho';
    }

    /**
     * Call Zoho CRM and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function zoho(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->zohoResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Zoho CRM and return the response as it is. `$path` is under `/crm/v7`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function zohoResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $login = app(CrmAccessTokens::class)->zoho($this->resolveCredentialData($run, $config));
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30)->send(strtoupper($method), $login['api'].'/crm/v7'.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho: the token was refused.',
            $response->status() === 403 => ' The Zoho user is not allowed to do that, or the refresh token lacks the scope.',
            $response->status() === 429 => ' Zoho is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The one result of a single-record write, or an error naming what Zoho said about the record.
     *
     * @param  array<string, mixed>  $result  a write's reply
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function written(array $result): array
    {
        $row = (array) (($result['data'] ?? [])[0] ?? []);

        if (($row['status'] ?? 'success') !== 'success') {
            $detail = is_array($row['details'] ?? null) && $row['details'] !== [] ? ' ('.json_encode($row['details']).')' : '';

            throw new RuntimeException('Zoho error: '.($row['message'] ?? 'the record was refused').' ['.($row['code'] ?? 'ERROR').']'.mb_substr($detail, 0, 200).'.');
        }

        return $row;
    }

    /**
     * A COQL query, paged by `LIMIT offset, count`, until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function coql(Run $run, array $config, string $select, int $limit): array
    {
        $records = [];

        do {
            $count = min(200, $limit - count($records));
            $page = $this->zohoResponse($run, $config, 'POST', '/coql', [], ['select_query' => $select.' limit '.count($records).', '.$count]);

            if ($page->status() === 204) {
                break;
            }

            $this->assertOk($page);
            $rows = array_values(array_filter((array) $page->json('data', []), 'is_array'));
            array_push($records, ...$rows);
        } while ((bool) $page->json('info.more_records') && count($records) < $limit && $rows !== []);

        return array_slice($records, 0, $limit);
    }

    /**
     * A module API name (`Leads`, `Custom_Module`), checked so it can sit in a URL or a query.
     *
     * @throws InvalidArgumentException
     */
    protected function apiName(string $name, string $label = 'The module'): string
    {
        $name = trim($name);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) === 1 ? $name : throw new InvalidArgumentException("{$label} must be an API name such as Leads or Custom_Module.");
    }

    /**
     * A record id (a long number), checked.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function recordId(array $config, string $key = 'record_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The record is required.');
        }

        return preg_match('/^\d{8,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException('The record is not a Zoho id (a long number).');
    }

    /**
     * The fields to write: the node's own settings (a lookup as `{id}`), then the JSON map for the rest.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $named  setting key => Zoho field
     * @param  list<string>  $lookups  the Zoho fields in `$named` that point at another record
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function writeFields(array $config, array $named, array $lookups = []): array
    {
        $extra = $config['fields'] ?? null;
        $extra = is_string($extra) ? (trim($extra) === '' ? [] : json_decode($extra, true)) : (array) $extra;

        if (! is_array($extra) || ($extra !== [] && array_is_list($extra))) {
            throw new InvalidArgumentException('The other fields must be a JSON object, e.g. {"Industry": "Retail"}.');
        }

        $fields = [];

        foreach ($extra as $name => $value) {
            $fields[$this->apiName((string) $name, 'A field')] = $value;
        }

        foreach ($named as $key => $field) {
            if (! filled($config[$key] ?? null)) {
                continue;
            }

            if (in_array($field, $lookups, true)) {
                if (preg_match('/^\d{8,25}$/', trim((string) $config[$key])) !== 1) {
                    throw new InvalidArgumentException("{$field} must be a Zoho id (a long number).");
                }

                $fields[$field] = ['id' => trim((string) $config[$key])];

                continue;
            }

            $fields[$field] = is_numeric($config[$key]) && in_array($field, ['Amount', 'Annual_Revenue', 'No_of_Employees'], true) ? $config[$key] + 0 : $config[$key];
        }

        return $fields;
    }

    /**
     * A value placed in a search criteria: Zoho wants backslashes before `\`, `(`, `)`, `,` and `:`.
     */
    protected function criteriaValue(string $value): string
    {
        return (string) preg_replace('/([\\\\(),:])/', '\\\\$1', $value);
    }

    /**
     * The field list a node reads: the defaults, then more names, each checked.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $defaults
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function selectFields(array $config, array $defaults): array
    {
        $extra = $config['read_fields'] ?? [];
        $extra = array_map(fn (mixed $name): string => $this->apiName((string) $name, 'A field'), array_filter(is_array($extra) ? $extra : explode(',', (string) $extra), fn (mixed $name): bool => trim((string) $name) !== ''));

        return array_values(array_unique([...$defaults, ...$extra]));
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 2000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
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
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function shapeRecord(array $record): array
    {
        $fields = [];

        foreach ($record as $key => $value) {
            $fields[$key] = is_array($value) && array_key_exists('id', $value) && ! array_is_list($value) ? ($value['name'] ?? $value['id']) : $value;

            if (is_array($value) && array_key_exists('id', $value) && ! array_is_list($value)) {
                $fields[$key.'_id'] = $value['id'];
            }
        }

        return ['id' => isset($record['id']) ? (string) $record['id'] : null, 'fields' => (object) $fields, 'created_at' => $record['Created_Time'] ?? null, 'updated_at' => $record['Modified_Time'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ownerField(string $title = 'Owner'): array
    {
        return Field::advanced(Field::dynamic($title, 'zoho.users', 'The Zoho user who owns it. Empty is the connected user.', 'Ada Lovelace'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraFieldsField(): array
    {
        return Field::advanced(Field::json('Other fields (JSON)', 'Any field by its API name, e.g. {"Industry": "Retail", "Region": "EMEA"}.'));
    }
}
