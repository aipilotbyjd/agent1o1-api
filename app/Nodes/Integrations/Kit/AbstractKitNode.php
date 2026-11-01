<?php

namespace App\Nodes\Integrations\Kit;

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
 * Shared request, error handling, validation and result shaping for the Kit
 * (ConvertKit) node family, on the v4 API. The key goes in `X-Kit-Api-Key`; lists
 * page by an opaque `after` cursor.
 *
 * A subscriber is given by email or by id: a field that takes one accepts either,
 * and the email is looked up once, so a value mapped from an earlier step works
 * as it is. Kit treats creating a subscriber that exists as an update, so
 * "add subscriber" is safe to run twice.
 */
abstract class AbstractKitNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.kit.com/v4';

    /**
     * The most Kit returns in one page.
     */
    protected const int PAGE_SIZE = 1000;

    public function category(): string
    {
        return 'kit';
    }

    /**
     * Call Kit and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function kit(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : array_filter($body, fn (mixed $value): bool => $value !== null);
        }

        return $this->decode(Http::withHeaders(['X-Kit-Api-Key' => $this->resolveAccessToken($run, $config)])->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options));
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $errors = array_filter((array) $response->json('errors'), 'is_string');
        $message = $errors !== [] ? implode('; ', $errors) : (string) ($response->json('message') ?? $response->json('error') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Kit: the API key was refused.',
            $response->status() === 429 => ' Kit is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Kit error: {$message}".(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page (cursor) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit): array
    {
        $records = [];
        $after = null;

        do {
            $page = (array) $this->kit($run, $config, 'GET', $path, query: [...$query, 'per_page' => min(self::PAGE_SIZE, $limit - count($records)), 'after' => $after]);
            array_push($records, ...array_values(array_filter((array) ($page[$key] ?? []), 'is_array')));
            $after = data_get($page, 'pagination.has_next_page') === true ? data_get($page, 'pagination.end_cursor') : null;
        } while ($after !== null && count($records) < $limit);

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
     * The id of a subscriber given as an id or an email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when there is no such subscriber
     */
    protected function subscriberId(Run $run, array $config, string $key = 'subscriber'): int
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The subscriber is required.');
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $email = $this->email([$key => $value], $key);
        $found = $this->kit($run, $config, 'GET', '/subscribers', query: ['email_address' => $email, 'status' => 'all', 'per_page' => 1]);
        $id = data_get($found, 'subscribers.0.id');

        return is_numeric($id) ? (int) $id : throw new InvalidArgumentException("Kit has no subscriber with the email \"{$email}\".");
    }

    /**
     * A numeric id from a setting (a tag, form, sequence, broadcast).
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
     * The body of a broadcast: subject, content, optional send time, tag filter and template.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function broadcastBody(array $config): array
    {
        $tags = array_values(array_filter(array_map('intval', array_filter(is_array($config['tag_ids'] ?? null) ? $config['tag_ids'] : explode(',', (string) ($config['tag_ids'] ?? '')), 'is_numeric'))));
        $at = filled($config['send_at'] ?? null) ? Carbon::parse((string) $config['send_at'])->utc() : null;

        if ($at !== null && $at->isPast()) {
            throw new InvalidArgumentException('The send time must be in the future.');
        }

        return [
            'subject' => $this->requiredString($config, 'subject', 'The subject'),
            'content' => $this->requiredString($config, 'content', 'The content'),
            'preview_text' => filled($config['preview_text'] ?? null) ? (string) $config['preview_text'] : null,
            'description' => filled($config['description'] ?? null) ? (string) $config['description'] : null,
            'public' => filter_var($config['public'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'send_at' => $at?->toIso8601String(),
            'email_template_id' => is_numeric($config['email_template_id'] ?? null) ? (int) $config['email_template_id'] : null,
            'subscriber_filter' => $tags === [] ? null : [['all' => [['type' => 'tag', 'ids' => $tags]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $subscriber
     * @return array<string, mixed>
     */
    protected function shapeSubscriber(array $subscriber): array
    {
        return [
            'id' => $subscriber['id'] ?? null,
            'email' => $subscriber['email_address'] ?? null,
            'first_name' => ($subscriber['first_name'] ?? '') !== '' ? $subscriber['first_name'] : null,
            'state' => $subscriber['state'] ?? null,
            'fields' => (object) ($subscriber['fields'] ?? []),
            'created_at' => $subscriber['created_at'] ?? null,
            'updated_at' => $subscriber['updated_at'] ?? null,
            'unsubscribed_at' => $subscriber['unsubscribed_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $broadcast
     * @return array<string, mixed>
     */
    protected function shapeBroadcast(array $broadcast): array
    {
        return [
            'id' => $broadcast['id'] ?? null,
            'subject' => $broadcast['subject'] ?? null,
            'description' => $broadcast['description'] ?? null,
            'preview_text' => $broadcast['preview_text'] ?? null,
            'public' => $broadcast['public'] ?? null,
            'public_url' => $broadcast['public_url'] ?? null,
            'created_at' => $broadcast['created_at'] ?? null,
            'send_at' => $broadcast['send_at'] ?? null,
            'published_at' => $broadcast['published_at'] ?? null,
            'recipients' => data_get($broadcast, 'stats.recipients'),
        ];
    }

    /**
     * The tag picker.
     *
     * @return array<string, mixed>
     */
    protected function tagField(string $title = 'Tag', ?string $description = null): array
    {
        return Field::dynamic($title, 'kit.tags', $description ?? 'Pick a tag.', 'customer', type: 'integer');
    }
}
