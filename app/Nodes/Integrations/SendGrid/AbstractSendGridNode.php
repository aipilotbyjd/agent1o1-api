<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the SendGrid
 * node family, on the v3 API. The key goes in a bearer header. Mail send answers
 * 202 with no body (the message id is in a header); marketing writes are queued
 * and answer 202 with a `job_id`, so a node that needs the result reads it back.
 *
 * A marketing contact is found by email, which SendGrid turns into an id.
 */
abstract class AbstractSendGridNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.sendgrid.com/v3';

    public function category(): string
    {
        return 'sendgrid';
    }

    /**
     * Call SendGrid and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function sendgrid(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $response = $this->sendgridResponse($run, $config, $method, $path, $body, $query);

        $this->assertOk($response);

        return $response->status() === 204 || $response->body() === '' ? null : $response->json();
    }

    /**
     * Call SendGrid and return the response as it is, for a node that reads a
     * header or treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     */
    protected function sendgridResponse(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $this->withoutNulls($body);
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

        $first = (array) ($response->json('errors.0') ?? []);
        $message = (string) ($first['message'] ?? "HTTP {$response->status()}");
        $field = $first['field'] ?? null;
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect SendGrid: the API key was refused.',
            $response->status() === 403 => ' The API key does not have the access this needs; edit its permissions in SendGrid.',
            $response->status() === 429 => ' SendGrid is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("SendGrid error: {$message}".($field ? " ({$field})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withoutNulls(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null);
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
     * Email addresses from a list or comma-separated text; an empty setting is an empty list.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function emails(array $config, string $key): array
    {
        $parts = is_array($config[$key] ?? null) ? $config[$key] : preg_split('/[\s,;]+/', (string) ($config[$key] ?? ''));

        return array_values(array_unique(array_map(fn (mixed $email): string => $this->email(['e' => $email], 'e'), array_filter((array) $parts, fn (mixed $email): bool => trim((string) $email) !== ''))));
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
     * Text that goes in a header-like place (a subject, a name): one line.
     */
    protected function oneLine(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 500): int
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
     * The id SendGrid keeps a contact under, found by email; null when there is no such contact.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    protected function findContact(Run $run, array $config, string $email): ?array
    {
        $response = $this->sendgridResponse($run, $config, 'POST', '/marketing/contacts/search/emails', ['emails' => [$email]]);

        if ($response->status() === 404) {
            return null;
        }

        $this->assertOk($response);

        $contact = ((array) $response->json('result'))[$email]['contact'] ?? null;

        return is_array($contact) ? $contact : null;
    }

    /**
     * A list or template id: SendGrid's are UUIDs, or `d-` followed by hex for a template.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function identifier(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} is not a valid id: pick one from the list.");
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return [
            'id' => $contact['id'] ?? null,
            'email' => $contact['email'] ?? null,
            'first_name' => $contact['first_name'] ?? null,
            'last_name' => $contact['last_name'] ?? null,
            'phone' => $contact['phone_number'] ?? null,
            'city' => $contact['city'] ?? null,
            'state' => $contact['state_province_region'] ?? null,
            'country' => $contact['country'] ?? null,
            'postal_code' => $contact['postal_code'] ?? null,
            'list_ids' => array_values((array) ($contact['list_ids'] ?? [])),
            'custom_fields' => (object) ($contact['custom_fields'] ?? []),
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
        ];
    }

    /**
     * A list picker.
     *
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'List'): array
    {
        return Field::dynamic($title, 'sendgrid.lists', 'Pick a marketing list.', 'Newsletter');
    }
}
