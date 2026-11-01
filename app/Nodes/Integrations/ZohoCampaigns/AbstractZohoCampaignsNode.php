<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Zoho
 * Campaigns node family. Campaigns shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called at the data center's `campaigns` address on its
 * v1.1 API: every call asks for JSON (`resfmt`), writes are form posts, a list pages
 * with `fromindex` (from 1) and `range`, and an error can arrive with a 200 status
 * and `"status": "error"`. Lists and campaigns are named by a key (a long hex string).
 */
abstract class AbstractZohoCampaignsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_campaigns';
    }

    /**
     * Call Zoho Campaigns and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params  query for a GET, form fields for a POST; null values are dropped
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function campaigns(Run $run, array $config, string $method, string $path, array $params = []): array
    {
        $login = app(CrmAccessTokens::class)->zohoApp($this->resolveCredentialData($run, $config), 'campaigns');
        $params = array_filter([...$params, 'resfmt' => 'JSON'], fn (mixed $value): bool => $value !== null);
        $request = Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30);

        $response = strtoupper($method) === 'GET'
            ? $request->get($login['base'].'/'.$path, $params)
            : $request->asForm()->post($login['base'].'/'.$path, $params);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful() && $response->json('status') !== 'error') {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Campaigns: the token was refused or lacks the Campaigns scopes.',
            $response->status() === 403 => ' The Zoho Campaigns user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Campaigns is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Campaigns error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $params, int $limit): array
    {
        $records = [];

        do {
            $want = min(200, $limit - count($records));
            $rows = array_values(array_filter((array) ($this->campaigns($run, $config, 'GET', $path, [...$params, 'fromindex' => count($records) + 1, 'range' => $want])[$key] ?? []), 'is_array'));
            array_push($records, ...$rows);
        } while (count($rows) === $want && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * A list or campaign key from a setting, checked so it can go in a query.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function key(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^[A-Za-z0-9]{10,80}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zoho Campaigns key (letters and digits).");
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

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException($email === '' ? 'The email address is required.' : "\"{$email}\" is not an email address.");
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
     * @param  array<string, mixed>  $list
     * @return array<string, mixed>
     */
    protected function shapeList(array $list): array
    {
        return ['key' => $list['listkey'] ?? null, 'name' => $list['listname'] ?? null, 'subscribers' => isset($list['noofcontacts']) ? (int) $list['noofcontacts'] : null, 'created_at' => $list['created_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $subscriber
     * @return array<string, mixed>
     */
    protected function shapeSubscriber(array $subscriber, ?string $listKey = null): array
    {
        $email = strtolower((string) ($subscriber['contact_email'] ?? $subscriber['Contact Email'] ?? ''));

        return ['id' => ($listKey !== null ? $listKey.':' : '').$email, 'email' => $email !== '' ? $email : null, 'first_name' => $subscriber['firstname'] ?? $subscriber['First Name'] ?? null, 'last_name' => $subscriber['lastname'] ?? $subscriber['Last Name'] ?? null, 'company' => $subscriber['companyname'] ?? null, 'status' => $subscriber['status'] ?? null, 'list_key' => $listKey, 'added_at' => $subscriber['added_time'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    protected function shapeCampaign(array $campaign): array
    {
        return ['id' => $campaign['campaign_key'] ?? null, 'name' => $campaign['campaign_name'] ?? null, 'subject' => $campaign['subject'] ?? null, 'status' => $campaign['campaign_status'] ?? null, 'sent_at' => $campaign['sent_time'] ?? null, 'created_at' => $campaign['created_time'] ?? null, 'url' => $campaign['campaign_preview_url'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function listField(string $title = 'Mailing list'): array
    {
        return Field::dynamic($title, 'zoho_campaigns.lists', null, 'Newsletter');
    }
}
