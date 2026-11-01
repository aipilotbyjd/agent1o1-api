<?php

namespace App\Nodes\Integrations\Stripe;

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
 * Shared request, error handling, validation and result shaping for the Stripe
 * node family. The connection is a secret key (`sk_`) or a restricted key
 * (`rk_`); whether it is live or test mode is the key's own.
 *
 * Stripe takes form-encoded bodies with nested names (`metadata[order]`), whole
 * numbers in the currency's smallest unit (cents) and `true`/`false` as words,
 * which `encode()` takes care of. An id is checked against its prefix before it
 * goes into a URL, so a value from an earlier step can't reach another path.
 */
abstract class AbstractStripeNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential {
        credentialFields as private baseCredentialFields;
    }

    protected const string API = 'https://api.stripe.com/v1';

    /**
     * Records Stripe returns in one page.
     */
    protected const int PAGE = 100;

    public function category(): string
    {
        return 'stripe';
    }

    /**
     * Call Stripe and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params  sent as the query of a GET and as the form body otherwise; null values are dropped
     * @param  string|null  $idempotencyKey  makes a repeated write return the first answer instead of doing it twice
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function stripe(Run $run, array $config, string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $request = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30);
        $params = $this->encode($params);
        $url = self::API.$path;
        $headers = array_filter([
            'Stripe-Account' => $this->connectedAccount($config),
            'Stripe-Version' => $this->apiVersion(),
            'Idempotency-Key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ]);

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'DELETE' => $request->asForm()->delete($url, $params),
            default => $request->asForm()->post($url, $params),
        };

        return $this->decode($response);
    }

    /**
     * The API version a node needs to be called with, when it can't rely on the
     * account's own (null leaves the account's version in charge).
     */
    protected function apiVersion(): ?string
    {
        return null;
    }

    /**
     * The connected account a Stripe Connect platform acts on, or null for the
     * account the key belongs to.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function connectedAccount(array $config): ?string
    {
        return $this->optionalId($config, 'stripe_account', 'acct', 'connected account id');
    }

    /**
     * The account picker, and the Connect account to act on.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function credentialFields(): array
    {
        return [
            ...$this->baseCredentialFields(),
            'stripe_account' => Field::advanced(Field::text('Connected account', 'For a Stripe Connect platform: act on this connected account instead of your own. Leave empty otherwise.', 'acct_...')),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function decode(Response $response): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        $message = (string) ($response->json('error.message') ?? "HTTP {$response->status()}");
        $param = $response->json('error.param');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Stripe: the key was refused.',
            $response->status() === 403 => ' The key can\'t do this. A restricted key needs permission for this resource.',
            $response->status() === 429 => ' Stripe is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Stripe error: {$message}".(is_string($param) && $param !== '' ? " (field: {$param})" : '').".{$hint}");
    }

    /**
     * Stripe's wire format: no nulls, words for booleans, the rest as text.
     *
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    protected function encode(array $params): array
    {
        $encoded = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $encoded[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => $this->encode($value),
                default => $value,
            };
        }

        return $encoded;
    }

    /**
     * An id with the prefix its kind of object has (`cus` for a customer), safe to
     * put in a path.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function stripeId(array $config, string $key, string $prefix, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("The {$label} is required.");
        }

        return preg_match('/^'.preg_quote($prefix, '/').'_[A-Za-z0-9_]+$/', $value) === 1
            ? $value
            : throw new InvalidArgumentException("\"{$value}\" is not a Stripe {$label}. It starts with {$prefix}_.");
    }

    /**
     * An id that is optional: null when empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $prefix, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->stripeId($config, $key, $prefix, $label) : null;
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
     * A whole amount in the currency's smallest unit (cents for USD).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function amount(array $config, string $key, string $label, int $minimum = 1): int
    {
        $value = $config[$key] ?? null;

        if (! is_numeric($value) || (int) $value != $value || (int) $value < $minimum) {
            throw new InvalidArgumentException("{$label} must be a whole number of at least {$minimum}, in the smallest unit of the currency (cents for USD).");
        }

        return (int) $value;
    }

    /**
     * A three-letter currency code, lower case as Stripe wants it.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function currency(array $config, string $key = 'currency'): string
    {
        $value = strtolower(trim((string) ($config[$key] ?? '')));

        return preg_match('/^[a-z]{3}$/', $value) === 1 ? $value : throw new InvalidArgumentException('The currency is a three-letter code such as usd or eur.');
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default = 25, int $max = 1000): int
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
     * The metadata to attach: flat key/value pairs, values as text.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>|null
     *
     * @throws InvalidArgumentException
     */
    protected function metadata(array $config, string $key = 'metadata'): ?array
    {
        $metadata = $this->decoded($config[$key] ?? null, 'Metadata');

        if ($metadata === null || $metadata === []) {
            return null;
        }

        return collect($metadata)
            ->reject(fn (mixed $value): bool => is_array($value) || $value === null)
            ->map(fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->all();
    }

    /**
     * The values among `$keys` that the step set, ready for a create or update.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    protected function filled(array $config, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            if (filled($config[$key] ?? null)) {
                $values[$key] = (string) $config[$key];
            }
        }

        return $values;
    }

    /**
     * A `created` filter from "after" and "before" times.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, int>|null
     */
    protected function createdRange(array $config, string $since = 'since', string $until = 'until'): ?array
    {
        $range = array_filter([
            'gte' => filled($config[$since] ?? null) ? Carbon::parse((string) $config[$since])->getTimestamp() : null,
            'lte' => filled($config[$until] ?? null) ? Carbon::parse((string) $config[$until])->getTimestamp() : null,
        ], fn (?int $value): bool => $value !== null);

        return $range === [] ? null : $range;
    }

    /**
     * Read every page of a list (newest first) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    protected function listAll(Run $run, array $config, string $path, array $params, int $limit): array
    {
        $items = [];
        $after = null;

        do {
            $page = $this->stripe($run, $config, 'GET', $path, [...$params, 'limit' => min(self::PAGE, $limit - count($items)), 'starting_after' => $after]);
            $records = array_values(array_filter((array) ($page['data'] ?? []), 'is_array'));
            array_push($items, ...$records);
            $after = data_get(end($records) ?: [], 'id');
            $more = (bool) ($page['has_more'] ?? false);
        } while ($more && $after !== null && count($items) < $limit);

        return ['items' => array_slice($items, 0, $limit), 'has_more' => $more || count($items) > $limit];
    }

    /**
     * A list node's output: the records under their name, how many, and whether
     * Stripe has more.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function listOutput(Run $run, array $config, string $path, string $name, array $params, int $default = 25): array
    {
        $result = $this->listAll($run, $config, $path, $params, $this->limit($config, $default));

        return [$name => $result['items'], 'count' => count($result['items']), 'has_more' => $result['has_more']];
    }

    /**
     * An event with the object it is about next to it, as the flow gets it.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeEvent(array $event): array
    {
        $object = (array) data_get($event, 'data.object', []);

        return [
            'id' => $event['id'] ?? null,
            'type' => $event['type'] ?? null,
            'created_at' => isset($event['created']) ? Carbon::createFromTimestampUTC((int) $event['created'])->toIso8601String() : null,
            'livemode' => $event['livemode'] ?? null,
            'account' => $event['account'] ?? null,
            'api_version' => $event['api_version'] ?? null,
            'request_id' => data_get($event, 'request.id'),
            'object_type' => $object['object'] ?? null,
            'object_id' => $object['id'] ?? null,
            'object' => $object,
            'previous_attributes' => (array) data_get($event, 'data.previous_attributes', []),
        ];
    }

    /**
     * The key every write that moves money or makes a record offers, so a step
     * that runs again can be made to do its work once.
     *
     * @return array<string, mixed>
     */
    protected function idempotencyField(): array
    {
        return Field::advanced(Field::text('Idempotency key', 'Running the step again with the same key returns the first result instead of doing it twice. Use something unique to the thing being done, such as an order id.'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function idempotencyKey(array $config): ?string
    {
        $key = trim((string) ($config['idempotency_key'] ?? ''));

        return $key === '' ? null : substr($key, 0, 255);
    }
}
