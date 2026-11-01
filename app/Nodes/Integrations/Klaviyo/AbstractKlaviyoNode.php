<?php

namespace App\Nodes\Integrations\Klaviyo;

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
 * Shared request, error handling, validation and result shaping for the Klaviyo
 * node family, on the JSON:API-style REST API. The private key goes in an
 * `Authorization: Klaviyo-API-Key …` header and every call names an API revision,
 * so a later change by Klaviyo doesn't alter what these nodes get back.
 *
 * A profile is given by its id or its email; the email is looked up once. Filters
 * are Klaviyo's own text (`equals(email,"a@b.co")`) and anything a person types
 * into one is quoted and escaped first.
 */
abstract class AbstractKlaviyoNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://a.klaviyo.com/api';

    /**
     * The API revision every call asks for.
     */
    private const string REVISION = '2024-10-15';

    public function category(): string
    {
        return 'klaviyo';
    }

    /**
     * Call Klaviyo and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function klaviyo(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Klaviyo-API-Key '.$this->resolveAccessToken($run, $config),
            'revision' => self::REVISION,
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ])->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);

        return $this->decode($response);
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $errors = collect((array) $response->json('errors'))->map(fn (mixed $error): string => is_array($error) ? trim((string) ($error['detail'] ?? $error['title'] ?? '')) : '')->filter()->unique()->implode('; ');
        $message = $errors !== '' ? $errors : "HTTP {$response->status()}";
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Klaviyo: the API key was refused.',
            $response->status() === 403 => ' The API key needs more access (give it the scope this step uses).',
            $response->status() === 429 => ' Klaviyo is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Klaviyo error: {$message}".(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page (following Klaviyo's cursor) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} the records and what was included with them
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit, int $pageSize = 100): array
    {
        $records = [];
        $included = [];
        $cursor = null;

        do {
            $page = (array) $this->klaviyo($run, $config, 'GET', $path, query: [...$query, 'page[size]' => min($pageSize, $limit - count($records)), 'page[cursor]' => $cursor]);
            array_push($records, ...array_values(array_filter((array) ($page['data'] ?? []), 'is_array')));
            array_push($included, ...array_values(array_filter((array) ($page['included'] ?? []), 'is_array')));
            $next = data_get($page, 'links.next');
            $cursor = null;

            if (is_string($next) && $next !== '') {
                parse_str((string) parse_url($next, PHP_URL_QUERY), $params);
                $cursor = $params['page']['cursor'] ?? null;
            }
        } while ($cursor !== null && count($records) < $limit);

        return [array_slice($records, 0, $limit), $included];
    }

    /**
     * A value as Klaviyo's filter language wants a string: quoted, with quotes and backslashes escaped.
     */
    protected function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
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

        if ($email === '') {
            throw new InvalidArgumentException('The email address is required.');
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException("\"{$email}\" is not an email address.");
    }

    /**
     * The id of a profile given as an id or an email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when there is no such profile
     */
    protected function profileId(Run $run, array $config, string $key = 'profile'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The profile is required.');
        }

        if (! str_contains($value, '@')) {
            return preg_match('/^[A-Za-z0-9]{20,30}$/', $value) === 1 ? $value : throw new InvalidArgumentException("\"{$value}\" is not a Klaviyo profile id or an email address.");
        }

        $email = $this->email([$key => $value], $key);
        $id = data_get($this->klaviyo($run, $config, 'GET', '/profiles', query: ['filter' => 'equals(email,'.$this->quote($email).')', 'page[size]' => 1]), 'data.0.id');

        return is_string($id) ? $id : throw new InvalidArgumentException("Klaviyo has no profile with the email \"{$email}\".");
    }

    /**
     * An id of the kind Klaviyo hands out (letters and digits).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function resourceId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return preg_match('/^[A-Za-z0-9]{4,40}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} is required: pick one from the list.");
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
     * A JSON setting that may arrive as text from a template.
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function decoded(mixed $value, string $label): ?array
    {
        if (is_string($value)) {
            if (trim($value) === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be valid JSON.");
        }

        return is_array($value) ? $value : null;
    }

    /**
     * The profile attributes a node sets from its settings, in Klaviyo's names.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function profileAttributes(array $config): array
    {
        $location = array_filter([
            'address1' => $config['address'] ?? null,
            'city' => $config['city'] ?? null,
            'region' => $config['region'] ?? null,
            'country' => $config['country'] ?? null,
            'zip' => $config['zip'] ?? null,
        ], fn (mixed $value): bool => filled($value));
        $phone = trim((string) ($config['phone'] ?? ''));

        if ($phone !== '' && preg_match('/^\+[1-9]\d{6,14}$/', preg_replace('/[\s().-]+/', '', $phone) ?? '') !== 1) {
            throw new InvalidArgumentException("\"{$phone}\" is not a phone number Klaviyo can use. Use international format with a leading +, e.g. +14155550123.");
        }

        return array_filter([
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'phone_number' => $phone !== '' ? preg_replace('/[\s().-]+/', '', $phone) : null,
            'external_id' => filled($config['external_id'] ?? null) ? (string) $config['external_id'] : null,
            'first_name' => filled($config['first_name'] ?? null) ? (string) $config['first_name'] : null,
            'last_name' => filled($config['last_name'] ?? null) ? (string) $config['last_name'] : null,
            'organization' => filled($config['organization'] ?? null) ? (string) $config['organization'] : null,
            'title' => filled($config['job_title'] ?? null) ? (string) $config['job_title'] : null,
            'location' => $location === [] ? null : $location,
            'properties' => $this->decoded($config['properties'] ?? null, 'The custom properties'),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function shapeProfile(array $profile): array
    {
        $attributes = (array) ($profile['attributes'] ?? []);

        return [
            'id' => $profile['id'] ?? null,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone_number'] ?? null,
            'external_id' => $attributes['external_id'] ?? null,
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'organization' => $attributes['organization'] ?? null,
            'job_title' => $attributes['title'] ?? null,
            'city' => data_get($attributes, 'location.city'),
            'region' => data_get($attributes, 'location.region'),
            'country' => data_get($attributes, 'location.country'),
            'zip' => data_get($attributes, 'location.zip'),
            'properties' => (object) ($attributes['properties'] ?? []),
            'email_consent' => data_get($attributes, 'subscriptions.email.marketing.consent'),
            'sms_consent' => data_get($attributes, 'subscriptions.sms.marketing.consent'),
            'created_at' => $attributes['created'] ?? null,
            'updated_at' => $attributes['updated'] ?? null,
            'last_event_at' => $attributes['last_event_date'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $list
     * @return array<string, mixed>
     */
    protected function shapeList(array $list): array
    {
        $attributes = (array) ($list['attributes'] ?? []);

        return ['id' => $list['id'] ?? null, 'name' => $attributes['name'] ?? null, 'opt_in_process' => $attributes['opt_in_process'] ?? null, 'created_at' => $attributes['created'] ?? null, 'updated_at' => $attributes['updated'] ?? null];
    }

    /**
     * The profile resource an add-to-list call names.
     *
     * @return list<array{type: string, id: string}>
     */
    protected function profileRefs(string ...$ids): array
    {
        return array_map(fn (string $id): array => ['type' => 'profile', 'id' => $id], $ids);
    }

    /**
     * A list picker.
     *
     * @return array<string, mixed>
     */
    protected function listField(string $description = 'Pick a list.'): array
    {
        return Field::dynamic('List', 'klaviyo.lists', $description, 'Newsletter');
    }

    /**
     * A profile field: email or id.
     *
     * @return array<string, mixed>
     */
    protected function profileField(): array
    {
        return Field::text('Profile', 'Their email address or Klaviyo id.', 'ada@example.com');
    }

    /**
     * The settings that describe a profile, shared by the nodes that write one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function profileFields(): array
    {
        return [
            'first_name' => Field::text('First name'),
            'last_name' => Field::text('Last name'),
            'phone' => Field::text('Phone', 'International format, e.g. +14155550123.'),
            'organization' => Field::advanced(Field::text('Company')),
            'job_title' => Field::advanced(Field::text('Job title')),
            'external_id' => Field::advanced(Field::text('External id', 'Your own id for this person.')),
            'address' => Field::advanced(Field::text('Address')),
            'city' => Field::advanced(Field::text('City')),
            'region' => Field::advanced(Field::text('State or region')),
            'country' => Field::advanced(Field::text('Country')),
            'zip' => Field::advanced(Field::text('Postal code')),
            'properties' => Field::advanced(Field::json('Custom properties (JSON)', 'Your own profile properties, e.g. {"plan": "pro"}.')),
        ];
    }
}
