<?php

namespace App\Nodes\Integrations\Brevo;

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
 * Shared request, error handling, validation and result shaping for the Brevo
 * (formerly Sendinblue) node family, on the v3 API. The key goes in an `api-key`
 * header. Brevo answers a write with 201 and an id, or 204 and nothing, so a node
 * that must return the record reads it back.
 *
 * A contact is given by email or by id; Brevo accepts either in the same place.
 * Contact fields (attributes) are upper-case names such as FNAME and LNAME.
 */
abstract class AbstractBrevoNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.brevo.com/v3';

    public function category(): string
    {
        return 'brevo';
    }

    /**
     * Call Brevo and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function brevo(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : array_filter($body, fn (mixed $value): bool => $value !== null);
        }

        return $this->decode(Http::withHeaders(['api-key' => $this->resolveAccessToken($run, $config)])->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options));
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Brevo: the API key was refused.',
            $response->status() === 429 => ' Brevo is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Brevo error: {$message}".($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page (`limit` and `offset`) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit, int $pageSize = 500): array
    {
        $records = [];

        do {
            $want = min($pageSize, $limit - count($records));
            $page = (array) $this->brevo($run, $config, 'GET', $path, query: [...$query, 'limit' => $want, 'offset' => count($records)]);
            $items = array_values(array_filter((array) ($page[$key] ?? []), 'is_array'));
            array_push($records, ...$items);
        } while (count($items) === $want && count($records) < $limit);

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
     * A contact's address in a URL: their email (encoded) or numeric id.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function contactPath(array $config, string $key = 'contact'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The contact is required.');
        }

        return '/contacts/'.(ctype_digit($value) ? $value : rawurlencode($this->email([$key => $value], $key)));
    }

    /**
     * A numeric id from a setting (a list, template, campaign).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): int
    {
        $value = $config[$key] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : throw new InvalidArgumentException("{$label} is required: pick one from the list.");
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
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function emailList(array $config): array
    {
        $parts = is_array($config['emails'] ?? null) ? $config['emails'] : explode(',', (string) ($config['emails'] ?? ''));
        $emails = array_values(array_unique(array_map(fn (mixed $email): string => $this->email(['e' => $email], 'e'), array_filter($parts, fn (mixed $email): bool => trim((string) $email) !== ''))));

        if ($emails === [] || count($emails) > 150) {
            throw new InvalidArgumentException('Give between 1 and 150 email addresses.');
        }

        return $emails;
    }

    /**
     * A list of ids from a setting (a list or comma-separated text).
     *
     * @return list<int>
     */
    protected function ids(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_map('intval', array_filter($parts, fn (mixed $part): bool => is_numeric(trim((string) $part)) && (int) trim((string) $part) > 0))));
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
     * The contact fields a node sets: first and last name, phone, and the JSON map.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function attributes(array $config): array
    {
        $attributes = (array) ($this->decoded($config['attributes'] ?? null, 'The contact fields') ?? []);

        foreach (['first_name' => 'FIRSTNAME', 'last_name' => 'LASTNAME', 'phone' => 'SMS'] as $key => $attribute) {
            if (filled($config[$key] ?? null)) {
                $attributes[$attribute] = (string) $config[$key];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        $attributes = (array) ($contact['attributes'] ?? []);

        return [
            'id' => $contact['id'] ?? null,
            'email' => $contact['email'] ?? null,
            'first_name' => $attributes['FIRSTNAME'] ?? $attributes['FNAME'] ?? null,
            'last_name' => $attributes['LASTNAME'] ?? $attributes['LNAME'] ?? null,
            'phone' => $attributes['SMS'] ?? null,
            'attributes' => (object) $attributes,
            'list_ids' => array_values(array_map('intval', (array) ($contact['listIds'] ?? []))),
            'email_blocked' => $contact['emailBlacklisted'] ?? false,
            'sms_blocked' => $contact['smsBlacklisted'] ?? false,
            'created_at' => $contact['createdAt'] ?? null,
            'modified_at' => $contact['modifiedAt'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    protected function shapeCampaign(array $campaign): array
    {
        $stats = (array) data_get($campaign, 'statistics.globalStats', []);

        return [
            'id' => $campaign['id'] ?? null,
            'name' => $campaign['name'] ?? null,
            'subject' => $campaign['subject'] ?? null,
            'status' => $campaign['status'] ?? null,
            'type' => $campaign['type'] ?? null,
            'sender' => data_get($campaign, 'sender.email'),
            'scheduled_at' => $campaign['scheduledAt'] ?? null,
            'sent_at' => $campaign['sentDate'] ?? null,
            'created_at' => $campaign['createdAt'] ?? null,
            'delivered' => $stats['delivered'] ?? null,
            'opens' => $stats['uniqueViews'] ?? null,
            'clicks' => $stats['uniqueClicks'] ?? null,
            'unsubscribed' => $stats['unsubscriptions'] ?? null,
        ];
    }

    /**
     * A list picker.
     *
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'List'): array
    {
        return Field::dynamic($title, 'brevo.lists', 'Pick a list.', 'Newsletter', type: 'integer');
    }

    /**
     * A contact field: email or id.
     *
     * @return array<string, mixed>
     */
    protected function contactField(): array
    {
        return Field::text('Contact', 'Their email address or Brevo id.', 'ada@example.com');
    }
}
