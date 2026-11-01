<?php

namespace App\Nodes\Integrations\Mailchimp;

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
 * Shared request, error handling, validation and result shaping for the
 * Mailchimp node family. The connection is an API key, which ends with the
 * data center the account lives on (`…-us21`): that decides the host, so a key
 * pasted without its ending is refused with a sentence about it.
 *
 * A member is addressed by the MD5 of their lower-case email (Mailchimp's
 * "subscriber hash"): the nodes take the email and work the hash out, so
 * "Alice@Example.com" and "alice@example.com" are the same person.
 */
abstract class AbstractMailchimpNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * The most records Mailchimp returns in one page.
     */
    protected const int PAGE_SIZE = 1000;

    public function category(): string
    {
        return 'mailchimp';
    }

    /**
     * The API host for a key, from the data center it ends with.
     *
     * @throws InvalidArgumentException when the key has none
     */
    public static function baseUrl(string $apiKey): string
    {
        $datacenter = str_contains($apiKey, '-') ? substr(strrchr($apiKey, '-'), 1) : '';

        return preg_match('/^[a-z]{2,4}\d{1,3}$/', $datacenter) === 1
            ? "https://{$datacenter}.api.mailchimp.com/3.0"
            : throw new InvalidArgumentException('A Mailchimp API key ends with its data center, like -us21. Copy the whole key.');
    }

    /**
     * Call Mailchimp and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body  null values are dropped
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function mailchimp(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $key = $this->resolveAccessToken($run, $config);
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : array_filter($body, fn (mixed $value): bool => $value !== null);
        }

        return $this->decode(Http::withBasicAuth('flows', $key)->acceptJson()->timeout(60)->send(strtoupper($method), self::baseUrl($key).$path, $options));
    }

    /**
     * @throws RuntimeException
     */
    protected function decode(Response $response): mixed
    {
        if ($response->successful()) {
            return $response->status() === 204 || $response->body() === '' ? null : $response->json();
        }

        $detail = collect((array) $response->json('errors'))->map(fn (mixed $item): string => is_array($item) ? trim(($item['field'] ?? '').' '.($item['message'] ?? '')) : '')->filter()->implode('; ');
        $message = (string) ($response->json('detail') ?? $response->json('title') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Mailchimp: the API key was refused.',
            $response->status() === 429 => ' Mailchimp is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Mailchimp error: {$message}".($detail !== '' ? " ({$detail})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * An audience id: ten letters or digits.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function listId(array $config, string $key = 'list_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The audience is required.');
        }

        return preg_match('/^[0-9a-f]{10}$/i', $value) === 1
            ? $value
            : throw new InvalidArgumentException("\"{$value}\" is not a Mailchimp audience id (ten letters and digits). Pick it from the list.");
    }

    /**
     * A checked email address from a setting.
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
     * The path of one member of an audience.
     *
     * @param  array<string, mixed>  $config
     */
    protected function memberPath(array $config): string
    {
        return '/lists/'.$this->listId($config).'/members/'.md5($this->email($config));
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
     * A campaign id.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function campaignId(array $config, string $key = 'campaign_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return preg_match('/^[0-9a-f]{8,12}$/i', $value) === 1 ? $value : throw new InvalidArgumentException('Pick a campaign, or give its id (about ten letters and digits).');
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
     * A list setting (or comma-separated text) as trimmed, non-empty strings.
     *
     * @return list<string>
     */
    protected function csv(mixed $value): array
    {
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $value = json_decode($value, true) ?: $value;
        }

        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));
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
     * Read a collection page by page (`count` and `offset`) until `$limit` records are in.
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
            $page = (array) $this->mailchimp($run, $config, 'GET', $path, query: [...$query, 'count' => $want, 'offset' => count($records), 'exclude_fields' => '_links,'.$key.'._links']);
            $items = array_values(array_filter((array) ($page[$key] ?? []), 'is_array'));
            array_push($records, ...$items);
        } while (count($items) === $want && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * The merge fields (FNAME, LNAME, PHONE, your own) a member node sets: the
     * JSON map plus the first name, last name and phone fields.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function mergeFields(array $config): array
    {
        $fields = (array) ($this->decoded($config['merge_fields'] ?? null, 'The merge fields') ?? []);

        foreach (['first_name' => 'FNAME', 'last_name' => 'LNAME', 'phone' => 'PHONE'] as $key => $merge) {
            if (filled($config[$key] ?? null)) {
                $fields[$merge] = (string) $config[$key];
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array<string, mixed>
     */
    protected function shapeMember(array $member): array
    {
        $merge = (array) ($member['merge_fields'] ?? []);

        return [
            'id' => $member['id'] ?? null,
            'email' => $member['email_address'] ?? null,
            'status' => $member['status'] ?? null,
            'first_name' => ($merge['FNAME'] ?? '') !== '' ? $merge['FNAME'] : null,
            'last_name' => ($merge['LNAME'] ?? '') !== '' ? $merge['LNAME'] : null,
            'phone' => ($merge['PHONE'] ?? '') !== '' ? $merge['PHONE'] : null,
            'merge_fields' => (object) $merge,
            'tags' => array_values(array_map(fn (array $tag): string => (string) $tag['name'], array_filter((array) ($member['tags'] ?? []), 'is_array'))),
            'vip' => $member['vip'] ?? false,
            'language' => ($member['language'] ?? '') !== '' ? $member['language'] : null,
            'email_type' => $member['email_type'] ?? null,
            'source' => $member['source'] ?? null,
            'rating' => $member['member_rating'] ?? null,
            'list_id' => $member['list_id'] ?? null,
            'subscribed_at' => ($member['timestamp_opt'] ?? '') !== '' ? $member['timestamp_opt'] : ($member['timestamp_signup'] ?? null),
            'last_changed' => $member['last_changed'] ?? null,
            'unsubscribe_reason' => $member['unsubscribe_reason'] ?? null,
            'open_rate' => data_get($member, 'stats.avg_open_rate'),
            'click_rate' => data_get($member, 'stats.avg_click_rate'),
        ];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    protected function shapeCampaign(array $campaign): array
    {
        return [
            'id' => $campaign['id'] ?? null,
            'type' => $campaign['type'] ?? null,
            'status' => $campaign['status'] ?? null,
            'title' => data_get($campaign, 'settings.title'),
            'subject' => data_get($campaign, 'settings.subject_line'),
            'from_name' => data_get($campaign, 'settings.from_name'),
            'reply_to' => data_get($campaign, 'settings.reply_to'),
            'list_id' => data_get($campaign, 'recipients.list_id'),
            'list_name' => data_get($campaign, 'recipients.list_name'),
            'recipient_count' => data_get($campaign, 'recipients.recipient_count'),
            'emails_sent' => $campaign['emails_sent'] ?? null,
            'created_at' => $campaign['create_time'] ?? null,
            'send_time' => ($campaign['send_time'] ?? '') !== '' ? $campaign['send_time'] : null,
            'url' => $campaign['archive_url'] ?? null,
            'web_id' => $campaign['web_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $list
     * @return array<string, mixed>
     */
    protected function shapeList(array $list): array
    {
        return [
            'id' => $list['id'] ?? null,
            'name' => $list['name'] ?? null,
            'member_count' => data_get($list, 'stats.member_count'),
            'unsubscribe_count' => data_get($list, 'stats.unsubscribe_count'),
            'cleaned_count' => data_get($list, 'stats.cleaned_count'),
            'open_rate' => data_get($list, 'stats.open_rate'),
            'click_rate' => data_get($list, 'stats.click_rate'),
            'double_opt_in' => $list['double_optin'] ?? null,
            'from_name' => data_get($list, 'campaign_defaults.from_name'),
            'from_email' => data_get($list, 'campaign_defaults.from_email'),
            'created_at' => $list['date_created'] ?? null,
            'web_id' => $list['web_id'] ?? null,
        ];
    }

    /**
     * The audience picker every audience node starts with.
     *
     * @return array<string, mixed>
     */
    protected function audienceField(string $description = 'Pick an audience.'): array
    {
        return Field::dynamic('Audience', 'mailchimp.audiences', $description, 'Newsletter');
    }

    /**
     * The campaign picker.
     *
     * @return array<string, mixed>
     */
    protected function campaignField(string $description = 'Pick a campaign, or give its id.'): array
    {
        return Field::dynamic('Campaign', 'mailchimp.campaigns', $description, 'March newsletter');
    }
}
