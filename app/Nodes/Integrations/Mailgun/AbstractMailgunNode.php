<?php

namespace App\Nodes\Integrations\Mailgun;

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
 * Shared request, error handling, validation and result shaping for the Mailgun
 * node family. The private key is the password of basic auth (user `api`). The
 * connection holds the key, the sending domain and the region (US or EU, which
 * are different hosts); a node can name another domain of the account.
 *
 * Mailgun reads forms, not JSON, and wants a repeated field (`to`, `o:tag`,
 * `event`) sent once per value, which PHP's own encoding does not do.
 */
abstract class AbstractMailgunNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'mailgun';
    }

    /**
     * Call Mailgun for the account's domain and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $form  fields sent as a form; a list value is sent once per item; null values are dropped
     * @param  array<string, mixed>  $query  same encoding
     *
     * @throws RuntimeException
     */
    protected function mailgun(Run $run, array $config, string $method, string $path, ?array $form = null, array $query = []): mixed
    {
        $response = $this->mailgunResponse($run, $config, $method, $path, $form, $query);

        $this->assertOk($response);

        return $response->status() === 204 || $response->body() === '' ? null : $response->json();
    }

    /**
     * Call Mailgun and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $form
     * @param  array<string, mixed>  $query
     */
    protected function mailgunResponse(Run $run, array $config, string $method, string $path, ?array $form = null, array $query = []): Response
    {
        [$key, , $host] = $this->login($run, $config);
        $queryString = $this->encode($query);
        $request = Http::withBasicAuth('api', $key)->acceptJson()->timeout(30);

        if ($form !== null) {
            $request = $request->withBody($this->encode($form), 'application/x-www-form-urlencoded');
        }

        return $request->send(strtoupper($method), "https://{$host}{$path}".($queryString === '' ? '' : '?'.$queryString));
    }

    /**
     * Follow a paging link Mailgun gave (`paging.next`) and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function mailgunUrl(Run $run, array $config, string $url): mixed
    {
        [$key, , $host] = $this->login($run, $config);

        // Only ever follow a link on the host we are already talking to.
        if (parse_url($url, PHP_URL_HOST) !== $host) {
            throw new RuntimeException('Mailgun sent a paging link to an unexpected address.');
        }

        $response = Http::withBasicAuth('api', $key)->acceptJson()->timeout(30)->get($url);

        $this->assertOk($response);

        return $response->json();
    }

    /**
     * The key, the sending domain and the API host for the step.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: string, 2: string}
     *
     * @throws InvalidArgumentException
     */
    protected function login(Run $run, array $config): array
    {
        try {
            $data = $this->resolveCredentialData($run, $config);
        } catch (RuntimeException $exception) {
            // A raw key in the step needs no connection; the domain then comes from the step too.
            if (trim((string) ($config['access_token'] ?? '')) === '') {
                throw $exception;
            }

            $data = [];
        }

        $key = trim((string) ($config['access_token'] ?? $data['api_key'] ?? ''));
        $domain = strtolower(trim((string) (($config['domain'] ?? '') !== '' ? $config['domain'] : ($data['domain'] ?? ''))));

        if ($key === '') {
            throw new InvalidArgumentException('Connect Mailgun: its API key is missing.');
        }

        return [$key, $domain, strtolower((string) ($data['region'] ?? '')) === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net'];
    }

    /**
     * The sending domain, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function domain(Run $run, array $config): string
    {
        $domain = $this->login($run, $config)[1];

        return preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) === 1 ? $domain : throw new InvalidArgumentException('Add the sending domain (like mg.example.com) to the Mailgun connection.');
    }

    /**
     * Form encoding that sends a list value once per item and drops nulls.
     *
     * @param  array<string, mixed>  $fields
     */
    protected function encode(array $fields): string
    {
        $pairs = [];

        foreach ($fields as $name => $value) {
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                if ($item !== null && $item !== '') {
                    $pairs[] = rawurlencode((string) $name).'='.rawurlencode(is_bool($item) ? ($item ? 'yes' : 'no') : (is_array($item) ? (string) json_encode($item) : (string) $item));
                }
            }
        }

        return implode('&', $pairs);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('error') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Mailgun: the API key was refused (the EU region uses a different host from the US).',
            $response->status() === 429 => ' Mailgun is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Mailgun error: {$message}".(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection by following `paging.next` until `$limit` items are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit, int $pageSize = 100, string $key = 'items'): array
    {
        $records = [];
        $page = (array) $this->mailgun($run, $config, 'GET', $path, query: [...$query, 'limit' => min($pageSize, $limit)]);

        while (true) {
            $items = array_values(array_filter((array) ($page[$key] ?? []), 'is_array'));
            array_push($records, ...$items);
            $next = (string) data_get($page, 'paging.next', '');

            if ($items === [] || $next === '' || count($records) >= $limit) {
                break;
            }

            $page = (array) $this->mailgunUrl($run, $config, $next);
        }

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
     * A mailing list address in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function listAddress(array $config): string
    {
        return rawurlencode($this->email(['list' => $config['list'] ?? null], 'list'));
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeEvent(array $event): array
    {
        $headers = (array) data_get($event, 'message.headers', []);

        return [
            'id' => $event['id'] ?? null,
            'event' => $event['event'] ?? null,
            'severity' => $event['severity'] ?? null,
            'recipient' => $event['recipient'] ?? null,
            'at' => isset($event['timestamp']) ? Carbon::createFromTimestampUTC((int) floor((float) $event['timestamp']))->toIso8601String() : null,
            'subject' => $headers['subject'] ?? null,
            'from' => $headers['from'] ?? null,
            'message_id' => $headers['message-id'] ?? null,
            'reason' => $event['reason'] ?? null,
            'status_code' => data_get($event, 'delivery-status.code'),
            'status_message' => data_get($event, 'delivery-status.message') ?? data_get($event, 'delivery-status.description'),
            'tags' => array_values((array) ($event['tags'] ?? [])),
            'variables' => (object) ($event['user-variables'] ?? []),
            'url' => $event['url'] ?? null,
            'ip' => $event['ip'] ?? null,
            'client' => data_get($event, 'client-info.client-name'),
            'country' => data_get($event, 'geolocation.country'),
        ];
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    protected function shapeMember(array $member): array
    {
        return [
            'email' => $member['address'] ?? null,
            'name' => $member['name'] ?? null,
            'subscribed' => (bool) ($member['subscribed'] ?? false),
            'vars' => (object) ($member['vars'] ?? []),
        ];
    }

    /**
     * A mailing list picker.
     *
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'Mailing list'): array
    {
        return Field::dynamic($title, 'mailgun.lists', 'Pick a mailing list.', 'news@mg.example.com', allowCustom: true);
    }
}
