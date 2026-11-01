<?php

namespace App\Nodes\Integrations\ZohoMail;

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
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Zoho Mail
 * node family. Mail shares the Zoho login of the other Zoho apps (see
 * `CrmAccessTokens`) and is called at the data center's `mail` address. Everything
 * sits under an account id: the connection's optional `account_id`, or the first
 * account the login can see. Answers are `{"status": {...}, "data": ...}`; a list
 * pages with `start` (from 1) and `limit` (at most 200). Ids are long numbers.
 */
abstract class AbstractZohoMailNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zoho_mail';
    }

    /**
     * Call Zoho Mail and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function mail(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->mailRaw($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call a path under the account (e.g. `/folders`).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    protected function account(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        return $this->mail($run, $config, $method, 'accounts/'.$this->accountId($run, $config).$path, $query, $body);
    }

    /**
     * The account the node works in: the connection's `account_id`, else the first the login sees.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function accountId(Run $run, array $config): string
    {
        $set = trim((string) ($this->resolveCredentialData($run, $config)['account_id'] ?? ''));

        if ($set !== '') {
            return preg_match('/^\d+$/', $set) === 1 ? $set : throw new InvalidArgumentException('The Zoho Mail account id in the connection must be a number.');
        }

        $first = (array) ($this->mail($run, $config, 'GET', 'accounts')['data'][0] ?? []);

        return isset($first['accountId']) ? (string) $first['accountId'] : throw new RuntimeException('Zoho Mail did not show an account for this login.');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function mailRaw(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $login = app(CrmAccessTokens::class)->zohoApp($this->resolveCredentialData($run, $config), 'mail');
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30)->send(strtoupper($method), $login['base'].'/'.$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('data.errorCode') ?? $response->json('status.description') ?? $response->json('data.moreInfo') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zoho Mail: the token was refused or lacks the Mail scopes.',
            $response->status() === 403 => ' The Zoho Mail user is not allowed to do that.',
            $response->status() === 429 => ' Zoho Mail is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zoho Mail error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit): array
    {
        $records = [];
        $account = $this->accountId($run, $config);

        do {
            $want = min(200, $limit - count($records));
            $rows = array_values(array_filter((array) ($this->mail($run, $config, 'GET', "accounts/{$account}{$path}", [...$query, 'start' => count($records) + 1, 'limit' => $want])['data'] ?? []), 'is_array'));
            array_push($records, ...$rows);
        } while (count($rows) === $want && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * A message or folder id (a long number) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d{6,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zoho Mail id (a long number).");
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?string
    {
        return filled($config[$key] ?? null) ? $this->numericId($config, $key, $label) : null;
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
     * Checked email addresses from a list or a comma separated setting.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function addresses(array $config, string $key, bool $required = false): array
    {
        $value = $config[$key] ?? null;
        $found = [];

        foreach (is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $address) {
            $address = strtolower(trim((string) $address));

            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException("\"{$address}\" is not an email address.");
            }

            $found[] = $address;
        }

        if ($required && $found === []) {
            throw new InvalidArgumentException('At least one email address is needed.');
        }

        return $found;
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
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message): array
    {
        $received = $message['receivedTime'] ?? null;

        return ['id' => isset($message['messageId']) ? (string) $message['messageId'] : null, 'folder_id' => isset($message['folderId']) ? (string) $message['folderId'] : null, 'subject' => $message['subject'] ?? null, 'from' => $message['fromAddress'] ?? $message['sender'] ?? null, 'from_name' => $message['sender'] ?? null, 'to' => $message['toAddress'] ?? null, 'summary' => $message['summary'] ?? null, 'unread' => ($message['status'] ?? null) !== null ? (string) $message['status'] === '0' : null, 'has_attachment' => ($message['hasAttachment'] ?? '0') === '1' || ($message['hasAttachment'] ?? false) === true, 'received_at' => is_numeric($received) ? gmdate('c', intdiv((int) $received, 1000)) : null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function folderField(string $title = 'Folder'): array
    {
        return Field::dynamic($title, 'zoho_mail.folders', null, 'Inbox');
    }
}
