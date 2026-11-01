<?php

namespace App\Nodes\Integrations\ActiveCampaign;

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
use Throwable;

/**
 * Shared request, error handling, validation and result shaping for the
 * ActiveCampaign node family. A connection is the account's own address
 * (`https://acme.api-us1.com`) and an API key, so these nodes read the account
 * whole with `resolveCredentialData`.
 *
 * The address is checked against ActiveCampaign's own domains before the key is
 * sent anywhere: a typo, or an address made up to collect keys, is refused.
 *
 * Money in ActiveCampaign is in cents; the nodes take and return the amount
 * as people write it (49.00).
 */
abstract class AbstractActiveCampaignNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * The most ActiveCampaign returns in one page.
     */
    protected const int PAGE_SIZE = 100;

    public function category(): string
    {
        return 'activecampaign';
    }

    /**
     * The API root for an account address, refused unless it is one of ActiveCampaign's own.
     *
     * @throws InvalidArgumentException
     */
    public static function apiRoot(string $accountUrl): string
    {
        $url = rtrim(trim($accountUrl), '/');

        return preg_match('#^https://[a-z0-9-]+\.(?:api-us1\.com|activehosted\.com|api-[a-z0-9]+\.com)$#i', $url) === 1
            ? $url.'/api/3'
            : throw new InvalidArgumentException('The ActiveCampaign account URL looks like https://youraccount.api-us1.com (Settings > Developer).');
    }

    /**
     * Call ActiveCampaign and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function ac(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $account = $this->resolveCredentialData($run, $config);
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return $this->decode(Http::withHeaders(['Api-Token' => trim((string) ($account['api_key'] ?? ''))])->acceptJson()->timeout(30)->send(strtoupper($method), self::apiRoot((string) ($account['api_url'] ?? '')).$path, $options));
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $errors = collect((array) $response->json('errors'))->map(fn (mixed $error): string => is_array($error) ? trim((string) ($error['title'] ?? $error['detail'] ?? '')) : (string) $error)->filter()->implode('; ');
        $message = $errors !== '' ? $errors : (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 403 => ' Check the API key and account URL on the ActiveCampaign connection.',
            $response->status() === 429 => ' ActiveCampaign is rate limiting the account (5 requests a second); try again in a moment.',
            default => '',
        };

        throw new RuntimeException("ActiveCampaign error: {$message}".(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page (`limit` and `offset`) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit): array
    {
        $records = [];

        do {
            $want = min(self::PAGE_SIZE, $limit - count($records));
            $page = (array) $this->ac($run, $config, 'GET', $path, query: [...$query, 'limit' => $want, 'offset' => count($records)]);
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
     * The id of a contact given as an id or an email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when there is no such contact
     */
    protected function contactId(Run $run, array $config, string $key = 'contact'): int
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The contact is required.');
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $email = $this->email([$key => $value], $key);
        $id = data_get($this->ac($run, $config, 'GET', '/contacts', query: ['email' => $email, 'limit' => 1]), 'contacts.0.id');

        return is_numeric($id) ? (int) $id : throw new InvalidArgumentException("ActiveCampaign has no contact with the email \"{$email}\".");
    }

    /**
     * A numeric id from a setting (a list, tag, automation, deal).
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
     * Custom field values from `{"7": "Acme"}` (field id => value) as ActiveCampaign wants them.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{field: string, value: mixed}>
     *
     * @throws InvalidArgumentException
     */
    protected function fieldValues(array $config): array
    {
        $values = [];

        foreach ((array) ($this->decoded($config['custom_fields'] ?? null, 'The custom fields') ?? []) as $field => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("The custom field \"{$field}\" must be a single value.");
            }

            $values[] = ['field' => (string) $field, 'value' => $value];
        }

        return $values;
    }

    /**
     * An amount as people write it (49.00) in ActiveCampaign's cents.
     *
     * @throws InvalidArgumentException
     */
    protected function cents(mixed $amount): int
    {
        return is_numeric($amount) && (float) $amount >= 0 ? (int) round((float) $amount * 100) : throw new InvalidArgumentException('The value must be a number, e.g. 49.00.');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array<int|string, mixed>  $fieldValues
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact, array $fieldValues = []): array
    {
        $fields = [];

        foreach (array_filter($fieldValues, 'is_array') as $value) {
            $fields[(string) ($value['field'] ?? '')] = $value['value'] ?? null;
        }

        return [
            'id' => isset($contact['id']) ? (int) $contact['id'] : null,
            'email' => $contact['email'] ?? null,
            'first_name' => ($contact['firstName'] ?? '') !== '' ? $contact['firstName'] : null,
            'last_name' => ($contact['lastName'] ?? '') !== '' ? $contact['lastName'] : null,
            'phone' => ($contact['phone'] ?? '') !== '' ? $contact['phone'] : null,
            'organization' => ($contact['orgname'] ?? '') !== '' ? $contact['orgname'] : null,
            'custom_fields' => (object) $fields,
            'created_at' => $this->iso($contact['cdate'] ?? null),
            'updated_at' => $this->iso($contact['udate'] ?? $contact['mdate'] ?? null),
            'ip' => $contact['ip'] ?? null,
            'score' => $contact['scoreValues'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $deal
     * @return array<string, mixed>
     */
    protected function shapeDeal(array $deal): array
    {
        $status = $deal['status'] ?? null;

        return [
            'id' => isset($deal['id']) ? (int) $deal['id'] : null,
            'title' => $deal['title'] ?? null,
            'description' => ($deal['description'] ?? '') !== '' ? $deal['description'] : null,
            'value' => isset($deal['value']) ? (int) $deal['value'] / 100 : null,
            'currency' => $deal['currency'] ?? null,
            'pipeline_id' => isset($deal['group']) ? (int) $deal['group'] : null,
            'stage_id' => isset($deal['stage']) ? (int) $deal['stage'] : null,
            'owner_id' => isset($deal['owner']) ? (int) $deal['owner'] : null,
            'contact_id' => isset($deal['contact']) ? (int) $deal['contact'] : null,
            'status' => match ((string) $status) {
                '0' => 'open', '1' => 'won', '2' => 'lost', default => null,
            },
            'created_at' => $this->iso($deal['cdate'] ?? null),
            'updated_at' => $this->iso($deal['mdate'] ?? null),
        ];
    }

    /**
     * ActiveCampaign's timestamp as ISO text.
     */
    protected function iso(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->toIso8601String();
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * A contact picker: email or id.
     *
     * @return array<string, mixed>
     */
    protected function contactField(): array
    {
        return Field::text('Contact', 'Their email address or ActiveCampaign id.', 'ada@example.com');
    }

    /**
     * A list picker.
     *
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'List'): array
    {
        return Field::dynamic($title, 'activecampaign.lists', 'Pick a list.', 'Newsletter', type: 'integer');
    }
}
