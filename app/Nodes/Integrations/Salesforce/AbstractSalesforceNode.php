<?php

namespace App\Nodes\Integrations\Salesforce;

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
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Salesforce
 * node family, on the REST API. The connection holds the org's My Domain and an
 * External Client App's key and secret; a session token is asked for with those
 * (client-credentials flow, see `CrmAccessTokens`) and used against the instance
 * Salesforce names. Records are `sObjects` named by API name (`Lead`,
 * `Invoice__c`), found by an 15 or 18 character id, or by SOQL, which pages with a
 * `nextRecordsUrl`.
 */
abstract class AbstractSalesforceNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    protected const string VERSION = 'v62.0';

    /**
     * For each typed object: the fields read by default.
     *
     * @var array<string, list<string>>
     */
    protected const array OBJECTS = [
        'Lead' => ['Id', 'FirstName', 'LastName', 'Company', 'Email', 'Phone', 'Status', 'LeadSource', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
        'Contact' => ['Id', 'FirstName', 'LastName', 'Email', 'Phone', 'Title', 'AccountId', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
        'Account' => ['Id', 'Name', 'Website', 'Industry', 'Phone', 'Type', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
        'Opportunity' => ['Id', 'Name', 'Amount', 'StageName', 'CloseDate', 'AccountId', 'IsClosed', 'IsWon', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
        'Case' => ['Id', 'CaseNumber', 'Subject', 'Status', 'Priority', 'ContactId', 'AccountId', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
        'Task' => ['Id', 'Subject', 'Status', 'Priority', 'ActivityDate', 'WhoId', 'WhatId', 'OwnerId', 'CreatedDate', 'SystemModstamp'],
    ];

    public function category(): string
    {
        return 'salesforce';
    }

    /**
     * Call Salesforce and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function salesforce(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->salesforceResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Salesforce and return the response as it is, for a node that treats a 404 as an answer.
     * `$path` is under `/services/data/{version}`, or a full `/services/…` path from a paging link.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function salesforceResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $login = app(CrmAccessTokens::class)->salesforce($this->resolveCredentialData($run, $config));
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        $url = $login['instance'].(str_starts_with($path, '/services/') ? $path : '/services/data/'.self::VERSION.$path);

        return Http::withToken($login['token'])->acceptJson()->timeout(30)->send(strtoupper($method), $url, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $first = (array) ($response->json('0') ?? $response->json() ?? []);
        $message = (string) ($first['message'] ?? $first['error_description'] ?? "HTTP {$response->status()}");
        $code = $first['errorCode'] ?? $first['error'] ?? null;
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Salesforce: the session was refused.',
            $response->status() === 403 => ' The run-as user of the connected app is not allowed to do that.',
            default => '',
        };

        throw new RuntimeException('Salesforce error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Run a SOQL query and read pages until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function query(Run $run, array $config, string $soql, int $limit): array
    {
        $records = [];
        $page = $this->salesforce($run, $config, 'GET', '/query', ['q' => $soql]);

        while (true) {
            array_push($records, ...array_values(array_filter((array) ($page['records'] ?? []), 'is_array')));
            $next = (string) ($page['nextRecordsUrl'] ?? '');

            if ($next === '' || count($records) >= $limit || ! str_starts_with($next, '/services/data/')) {
                break;
            }

            $page = $this->salesforce($run, $config, 'GET', $next);
        }

        return array_slice($records, 0, $limit);
    }

    /**
     * An sObject API name (`Lead`, `Invoice__c`), checked so it can sit in a URL or a query.
     *
     * @throws InvalidArgumentException
     */
    protected function apiName(string $name, string $label = 'The object'): string
    {
        $name = trim($name);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) === 1 ? $name : throw new InvalidArgumentException("{$label} must be an API name such as Lead or Invoice__c.");
    }

    /**
     * A record id (15 or 18 letters and digits), checked.
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

        return preg_match('/^[A-Za-z0-9]{15}([A-Za-z0-9]{3})?$/', $value) === 1 ? $value : throw new InvalidArgumentException('The record is not a Salesforce id (15 or 18 letters and digits).');
    }

    /**
     * A value placed in SOQL as text: quoted, with quotes and backslashes escaped.
     */
    protected function soqlText(string $value): string
    {
        return "'".str_replace(['\\', "'", "\r", "\n"], ['\\\\', "\\'", ' ', ' '], $value)."'";
    }

    /**
     * The field list of a query: the defaults for a typed object, then more names, each checked.
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
     * The fields to write: the node's own settings, then the JSON map for the rest.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $named  setting key => Salesforce field
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function writeFields(array $config, array $named): array
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
            if (filled($config[$key] ?? null)) {
                $fields[$field] = is_numeric($config[$key]) && in_array($field, ['Amount', 'Probability', 'AnnualRevenue', 'NumberOfEmployees'], true) ? $config[$key] + 0 : $config[$key];
            }
        }

        return $fields;
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
        $type = data_get($record, 'attributes.type');
        $fields = $record;
        unset($fields['attributes']);

        return ['id' => $record['Id'] ?? $record['id'] ?? null, 'object' => $type, 'fields' => (object) $fields, 'created_at' => $record['CreatedDate'] ?? null, 'updated_at' => $record['SystemModstamp'] ?? $record['LastModifiedDate'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ownerField(string $title = 'Owner'): array
    {
        return Field::advanced(Field::dynamic($title, 'salesforce.users', 'The Salesforce user who owns it. Empty is the run-as user.', 'Ada Lovelace'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraFieldsField(): array
    {
        return Field::advanced(Field::json('Other fields (JSON)', 'Any field by its API name, e.g. {"Industry": "Retail", "Region__c": "EMEA"}.'));
    }
}
