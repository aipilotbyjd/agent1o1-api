<?php

namespace App\Nodes\Integrations\HubSpot;

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
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the HubSpot
 * node family, on the CRM v3 API. The private app token goes in a bearer header.
 * Contacts, companies, deals and tickets are all "objects" with a `properties`
 * map of text values, found by id or searched with filters; a search pages with
 * an `after` cursor and returns at most 10 000 records.
 */
abstract class AbstractHubSpotNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.hubapi.com';

    /**
     * For each object: the properties read by default, the property that holds when it was last
     * changed, and the id of the association from a note and from a task to it.
     *
     * @var array<string, array{properties: list<string>, modified: string, note: int, task: int}>
     */
    protected const array OBJECTS = [
        'contacts' => ['properties' => ['firstname', 'lastname', 'email', 'phone', 'company', 'jobtitle', 'lifecyclestage', 'hs_lead_status', 'hubspot_owner_id', 'createdate', 'lastmodifieddate'], 'modified' => 'lastmodifieddate', 'note' => 202, 'task' => 204],
        'companies' => ['properties' => ['name', 'domain', 'industry', 'phone', 'city', 'country', 'numberofemployees', 'hubspot_owner_id', 'createdate', 'hs_lastmodifieddate'], 'modified' => 'hs_lastmodifieddate', 'note' => 190, 'task' => 192],
        'deals' => ['properties' => ['dealname', 'amount', 'dealstage', 'pipeline', 'closedate', 'hubspot_owner_id', 'createdate', 'hs_lastmodifieddate'], 'modified' => 'hs_lastmodifieddate', 'note' => 214, 'task' => 216],
        'tickets' => ['properties' => ['subject', 'content', 'hs_pipeline', 'hs_pipeline_stage', 'hs_ticket_priority', 'hubspot_owner_id', 'createdate', 'hs_lastmodifieddate'], 'modified' => 'hs_lastmodifieddate', 'note' => 228, 'task' => 230],
    ];

    public function category(): string
    {
        return 'hubspot';
    }

    /**
     * Call HubSpot and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|list<mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function hubspot(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->hubspotResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call HubSpot and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|list<mixed>|null  $body
     */
    protected function hubspotResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
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

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $category = $response->json('category');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect HubSpot: the token was refused.',
            $response->status() === 403 => ' The private app does not have the scope this needs: edit it in HubSpot > Settings > Private Apps.',
            $response->status() === 429 => ' HubSpot is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('HubSpot error: '.mb_substr($message, 0, 400).($category ? " ({$category})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Search an object type and read pages until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>  $filters  one AND group of filters
     * @param  list<string>  $properties
     * @return list<array<string, mixed>>
     */
    protected function search(Run $run, array $config, string $object, array $filters, array $properties, int $limit, ?string $sortBy = null, string $direction = 'DESCENDING'): array
    {
        $records = [];
        $after = null;

        do {
            $page = $this->hubspot($run, $config, 'POST', "/crm/v3/objects/{$object}/search", [], array_filter([
                'filterGroups' => $filters === [] ? [] : [['filters' => $filters]],
                'properties' => $properties,
                'sorts' => $sortBy === null ? null : [['propertyName' => $sortBy, 'direction' => $direction]],
                'limit' => min(100, $limit - count($records)),
                'after' => $after,
            ], fn (mixed $value): bool => $value !== null));
            array_push($records, ...array_values(array_filter((array) ($page['results'] ?? []), 'is_array')));
            $after = data_get($page, 'paging.next.after');
        } while ($after !== null && count($records) < $limit && count($records) < 10000);

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

        return preg_match('/^\d+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a HubSpot id (a number).");
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
     * A property name (letters, digits, underscore), checked.
     *
     * @throws InvalidArgumentException
     */
    protected function propertyName(string $name): string
    {
        $name = trim($name);

        return preg_match('/^[A-Za-z0-9_]+$/', $name) === 1 ? $name : throw new InvalidArgumentException("\"{$name}\" is not a HubSpot property name (letters, digits and underscores).");
    }

    /**
     * The properties to write: the ones the node's own fields set, then the JSON map for the rest.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $named  setting key => HubSpot property
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    protected function properties(array $config, array $named): array
    {
        $extra = $config['properties'] ?? null;
        $extra = is_string($extra) ? (trim($extra) === '' ? [] : json_decode($extra, true)) : (array) $extra;

        if (! is_array($extra) || ($extra !== [] && array_is_list($extra))) {
            throw new InvalidArgumentException('The other properties must be a JSON object, e.g. {"city": "Berlin"}.');
        }

        $properties = [];

        foreach ($extra as $name => $value) {
            $properties[$this->propertyName((string) $name)] = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : (string) json_encode($value));
        }

        foreach ($named as $key => $property) {
            if (filled($config[$key] ?? null)) {
                $properties[$property] = (string) $config[$key];
            }
        }

        return $properties;
    }

    /**
     * A date setting as HubSpot wants it in a property: ISO 8601 in UTC; null when empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function date(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->format('Y-m-d\TH:i:s.v\Z') : null;
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
     * The extra property names a node should read besides the defaults.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function readProperties(array $config, string $object): array
    {
        $extra = $config['read_properties'] ?? [];
        $extra = array_map(fn (mixed $name): string => $this->propertyName((string) $name), array_filter(is_array($extra) ? $extra : explode(',', (string) $extra), fn (mixed $name): bool => trim((string) $name) !== ''));

        return array_values(array_unique([...self::OBJECTS[$object]['properties'], ...$extra]));
    }

    /**
     * The object type a node works on, checked against the four HubSpot objects.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function objectOf(array $config, string $key = 'object'): string
    {
        $object = (string) ($config[$key] ?? 'contacts');

        return isset(self::OBJECTS[$object]) ? $object : throw new InvalidArgumentException("\"{$object}\" is not a HubSpot record type.");
    }

    /**
     * The search filters of a node: the filters given as JSON, else the property, operator and value fields.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function filters(array $config): array
    {
        $operators = ['EQ', 'NEQ', 'CONTAINS_TOKEN', 'NOT_CONTAINS_TOKEN', 'GT', 'GTE', 'LT', 'LTE', 'HAS_PROPERTY', 'NOT_HAS_PROPERTY'];
        $given = $config['filters'] ?? null;
        $given = is_string($given) ? (trim($given) === '' ? null : json_decode($given, true)) : $given;

        if ($given === null && blank($config['property'] ?? null)) {
            return [];
        }

        $list = $given ?? [['propertyName' => $config['property'] ?? '', 'operator' => $config['operator'] ?? 'EQ', 'value' => $config['value'] ?? '']];

        if (! is_array($list) || ! array_is_list($list) || $list === [] || count($list) > 6) {
            throw new InvalidArgumentException('Give the filters as a JSON list of up to 6 conditions.');
        }

        return array_map(function (mixed $filter) use ($operators): array {
            if (! is_array($filter)) {
                throw new InvalidArgumentException('Each filter needs a propertyName, an operator and a value.');
            }

            $operator = strtoupper((string) ($filter['operator'] ?? 'EQ'));

            if (! in_array($operator, $operators, true)) {
                throw new InvalidArgumentException("\"{$operator}\" is not a search operator.");
            }

            $condition = ['propertyName' => $this->propertyName((string) ($filter['propertyName'] ?? '')), 'operator' => $operator];

            if (! str_ends_with($operator, 'HAS_PROPERTY')) {
                $condition['value'] = is_scalar($filter['value'] ?? null) ? (string) $filter['value'] : throw new InvalidArgumentException('Each filter needs a value.');
            }

            return $condition;
        }, $list);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function shapeRecord(array $record): array
    {
        return [
            'id' => isset($record['id']) ? (string) $record['id'] : null,
            'properties' => (object) ($record['properties'] ?? []),
            'created_at' => $record['createdAt'] ?? null,
            'updated_at' => $record['updatedAt'] ?? null,
            'archived' => (bool) ($record['archived'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ownerField(string $title = 'Owner'): array
    {
        return Field::advanced(Field::dynamic($title, 'hubspot.owners', 'The HubSpot user who owns it.', 'Ada Lovelace'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraPropertiesField(): array
    {
        return Field::advanced(Field::json('Other properties (JSON)', 'Any HubSpot property by its internal name, e.g. {"city": "Berlin", "lifecyclestage": "lead"}.'));
    }
}
