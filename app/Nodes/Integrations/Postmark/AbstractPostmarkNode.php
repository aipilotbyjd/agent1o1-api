<?php

namespace App\Nodes\Integrations\Postmark;

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
 * Shared request, error handling, validation and result shaping for the Postmark
 * node family. The server's token goes in `X-Postmark-Server-Token`; the few
 * account-wide calls (senders, domains) use the account token in
 * `X-Postmark-Account-Token`. Lists page with `count` and `offset` (Postmark
 * stops at 10 000 records in all), and every field is in PascalCase.
 */
abstract class AbstractPostmarkNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.postmarkapp.com';

    public function category(): string
    {
        return 'postmark';
    }

    /**
     * Call Postmark and return the decoded body (null for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|list<mixed>|null  $body  null values are dropped from an object
     * @param  array<string, mixed>  $query  null values are dropped
     *
     * @throws RuntimeException
     */
    protected function postmark(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], bool $account = false): mixed
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '')];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : (array_is_list($body) ? $body : array_filter($body, fn (mixed $value): bool => $value !== null));
        }

        $header = $account ? ['X-Postmark-Account-Token' => $this->accountToken($run, $config)] : ['X-Postmark-Server-Token' => $this->resolveAccessToken($run, $config)];
        $response = $this->send($header, $method, $path, $options);

        $this->assertOk($response);

        return $response->status() === 204 || $response->body() === '' ? null : $response->json();
    }

    /**
     * @param  array<string, string>  $header
     * @param  array<string, mixed>  $options
     */
    private function send(array $header, string $method, string $path, array $options): Response
    {
        return Http::withHeaders($header)->acceptJson()->timeout(30)->send(strtoupper($method), self::API_URL.$path, $options);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function accountToken(Run $run, array $config): string
    {
        $token = trim((string) ($this->resolveCredentialData($run, $config)['account_token'] ?? ''));

        return $token !== '' ? $token : throw new InvalidArgumentException('This needs the Account API token: add it to the Postmark connection (Postmark > Account > API tokens).');
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('Message') ?? "HTTP {$response->status()}");
        $code = $response->json('ErrorCode');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Postmark: the token was refused.',
            $response->status() === 429 => ' Postmark is rate limiting the server; try again in a moment.',
            default => '',
        };

        throw new RuntimeException("Postmark error: {$message}".($code ? " (code {$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a collection page by page (`count` and `offset`) until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit, int $pageSize = 500): array
    {
        $records = [];

        do {
            $want = min($pageSize, $limit - count($records), 10000 - count($records));
            $page = (array) $this->postmark($run, $config, 'GET', $path, query: [...$query, 'count' => $want, 'offset' => count($records)]);
            $items = array_values(array_filter((array) ($page[$key] ?? []), 'is_array'));
            array_push($records, ...$items);
        } while ($want > 0 && count($items) === $want && count($records) < $limit && count($records) < 10000);

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
     * "Name <address>" (or the bare address) for a From setting.
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
     * An id placed in a URL.
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
     * A date and time setting as Postmark wants it in a filter: UTC, without a zone.
     *
     * @param  array<string, mixed>  $config
     */
    protected function moment(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->format('Y-m-d\TH:i:s') : null;
    }

    /**
     * A date setting as Postmark wants it in the stats: the day, in UTC.
     *
     * @param  array<string, mixed>  $config
     */
    protected function day(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->toDateString() : null;
    }

    /**
     * The recipients of a message, as a list of addresses.
     *
     * @param  array<string, mixed>  $message
     * @return list<string>
     */
    protected function recipients(array $message): array
    {
        $to = $message['To'] ?? [];

        return array_values(array_filter(array_map(fn (mixed $recipient): string => is_array($recipient) ? (string) ($recipient['Email'] ?? '') : (string) $recipient, is_array($to) ? $to : [$to])));
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message): array
    {
        return [
            'id' => $message['MessageID'] ?? null,
            'from' => $message['From'] ?? null,
            'to' => $this->recipients($message),
            'subject' => $message['Subject'] ?? null,
            'status' => $message['Status'] ?? null,
            'tag' => $message['Tag'] ?? null,
            'stream' => $message['MessageStream'] ?? null,
            'sent_at' => $message['ReceivedAt'] ?? $message['Date'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $bounce
     * @return array<string, mixed>
     */
    protected function shapeBounce(array $bounce): array
    {
        return [
            'id' => $bounce['ID'] ?? null,
            'type' => $bounce['Type'] ?? null,
            'email' => $bounce['Email'] ?? null,
            'message_id' => $bounce['MessageID'] ?? null,
            'subject' => $bounce['Subject'] ?? null,
            'details' => $bounce['Details'] ?? null,
            'bounced_at' => $bounce['BouncedAt'] ?? null,
            'inactive' => (bool) ($bounce['Inactive'] ?? false),
            'can_activate' => (bool) ($bounce['CanActivate'] ?? false),
            'tag' => $bounce['Tag'] ?? null,
        ];
    }

    /**
     * A received email in the flat shape both inbound nodes return.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function inbound(array $message): array
    {
        return [
            'id' => $message['MessageID'] ?? null,
            'from' => $message['From'] ?? null,
            'from_name' => $message['FromName'] ?? null,
            'to' => $this->recipients($message),
            'cc' => array_values(array_filter(array_map(fn (mixed $cc): string => is_array($cc) ? (string) ($cc['Email'] ?? '') : '', (array) ($message['CcFull'] ?? [])))),
            'subject' => $message['Subject'] ?? null,
            'date' => $message['Date'] ?? null,
            'mailbox_hash' => $message['MailboxHash'] ?? null,
            'tag' => $message['Tag'] ?? null,
            'text' => $message['TextBody'] ?? null,
            'html' => $message['HtmlBody'] ?? null,
            'reply_text' => $message['StrippedTextReply'] ?? null,
            'attachments' => array_values(array_map(fn (array $file): array => ['name' => $file['Name'] ?? null, 'content_type' => $file['ContentType'] ?? null, 'size' => $file['ContentLength'] ?? null], array_filter((array) ($message['Attachments'] ?? []), 'is_array'))),
        ];
    }

    /**
     * The addresses of a suppression change: between 1 and 50.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function suppressionList(array $config): array
    {
        $emails = $this->emails($config, 'emails');

        return $emails !== [] && count($emails) <= 50 ? $emails : throw new InvalidArgumentException('Give between 1 and 50 email addresses.');
    }

    /**
     * What a suppression change did for each address.
     *
     * @param  array<string, mixed>  $found
     * @return array<string, mixed>
     */
    protected function suppressionResult(array $found, string $done): array
    {
        $rows = array_values(array_filter((array) ($found['Suppressions'] ?? []), 'is_array'));
        $failed = array_values(array_map(fn (array $row): array => ['email' => $row['EmailAddress'] ?? null, 'error' => $row['Message'] ?? 'Refused'], array_filter($rows, fn (array $row): bool => ($row['Status'] ?? '') === 'Failed')));

        return [$done => count($rows) - count($failed), 'failed' => $failed];
    }

    /**
     * A message stream picker.
     *
     * @return array<string, mixed>
     */
    protected function streamField(string $title = 'Message stream'): array
    {
        return Field::advanced(Field::dynamic($title, 'postmark.streams', 'Empty is the default transactional stream.', 'outbound', allowCustom: true));
    }

    /**
     * The stream chosen, defaulting to the transactional one.
     *
     * @param  array<string, mixed>  $config
     */
    protected function stream(array $config): string
    {
        $stream = trim((string) ($config['stream'] ?? ''));

        return $stream !== '' ? $stream : 'outbound';
    }
}
