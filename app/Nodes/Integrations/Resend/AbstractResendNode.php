<?php

namespace App\Nodes\Integrations\Resend;

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
 * Shared request, error handling, validation and result shaping for the Resend
 * node family. The key goes in a bearer header; lists page by cursor (`after` the
 * last id, `has_more`). A contact is found by its id or its email, which Resend
 * accepts in the same place.
 */
abstract class AbstractResendNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.resend.com';

    public function category(): string
    {
        return 'resend';
    }

    /**
     * Call Resend and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|list<mixed>|null  $body  null values are dropped from an object
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, string>  $headers
     *
     * @throws RuntimeException
     */
    protected function resend(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], array $headers = []): mixed
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : (array_is_list($body) ? $body : array_filter($body, fn (mixed $value): bool => $value !== null));
        }

        $response = Http::withToken($this->resolveAccessToken($run, $config))->withHeaders($headers)->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);

        $this->assertOk($response);

        return $response->status() === 204 || $response->body() === '' ? null : $response->json();
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
        $name = $response->json('name');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Resend: the API key was refused or is limited to sending.',
            $response->status() === 403 => ' The sending domain is not verified, or the key does not have access.',
            $response->status() === 429 => ' Resend is rate limiting the account (a few requests a second); try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Resend error: {$message}".($name ? " ({$name})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page, newest first, until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $records = [];

        do {
            $page = (array) $this->resend($run, $config, 'GET', $path, query: [...$query, 'limit' => min(100, $limit - count($records)), 'after' => $records === [] ? null : end($records)['id'] ?? null]);
            $items = array_values(array_filter((array) ($page['data'] ?? []), 'is_array'));
            array_push($records, ...$items);
        } while (($page['has_more'] ?? false) && $items !== [] && count($records) < $limit);

        return array_slice($records, 0, $limit);
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
     * Email addresses from a list or text; an empty setting is an empty list.
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
     * "Name <address>" (or the bare address) for a From setting; the name is kept to one line without brackets or quotes.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function sender(array $config): string
    {
        $address = $this->email($config, 'sender_email');
        $name = $this->oneLine(preg_replace('/[<>"]+/', ' ', (string) ($config['sender_name'] ?? '')));

        return $name === '' ? $address : "{$name} <{$address}>";
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
     * An id or email placed in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathPart(array $config, string $key, string $label): string
    {
        return rawurlencode($this->requiredString($config, $key, $label));
    }

    /**
     * Text that goes in a subject or header: one line.
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
     * @param  array<string, mixed>  $email
     * @return array<string, mixed>
     */
    protected function shapeEmail(array $email): array
    {
        return [
            'id' => $email['id'] ?? null,
            'from' => $email['from'] ?? null,
            'to' => array_values((array) ($email['to'] ?? [])),
            'subject' => $email['subject'] ?? null,
            'status' => $email['last_event'] ?? null,
            'created_at' => $email['created_at'] ?? null,
            'scheduled_at' => $email['scheduled_at'] ?? null,
        ];
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
            'unsubscribed' => (bool) ($contact['unsubscribed'] ?? false),
            'created_at' => $contact['created_at'] ?? null,
        ];
    }

    /**
     * An audience picker.
     *
     * @return array<string, mixed>
     */
    protected function audienceField(string $title = 'Audience'): array
    {
        return Field::dynamic($title, 'resend.audiences', 'Pick an audience.', 'General');
    }
}
