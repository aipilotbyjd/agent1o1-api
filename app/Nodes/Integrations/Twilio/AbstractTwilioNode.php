<?php

namespace App\Nodes\Integrations\Twilio;

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
use Throwable;

/**
 * Shared request, error handling, validation and result shaping for the Twilio
 * node family. A Twilio connection is an account SID and its auth token (two
 * fields, so these nodes read the account whole with `resolveCredentialData`),
 * sent as HTTP basic auth. Twilio takes form-encoded bodies, and answers a
 * failure with a status and `{code, message}`.
 *
 * Phone numbers are given in international format (`+14155550123`, with a
 * leading +); a `whatsapp:` prefix is added by the WhatsApp nodes. A person who
 * types `(415) 555-0123` gets a sentence telling them what Twilio needs instead
 * of a charge for a message to the wrong number.
 */
abstract class AbstractTwilioNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * Hosts of the Twilio services the nodes talk to.
     *
     * @var array<string, string>
     */
    private const array HOSTS = ['api' => 'https://api.twilio.com', 'lookups' => 'https://lookups.twilio.com', 'verify' => 'https://verify.twilio.com'];

    /**
     * The most Twilio returns in one page.
     */
    protected const int PAGE_SIZE = 1000;

    public function category(): string
    {
        return 'twilio';
    }

    /**
     * The connected account's SID and auth token.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when the connection lacks one
     */
    protected function account(Run $run, array $config): array
    {
        $data = $this->resolveCredentialData($run, $config);
        $sid = trim((string) ($data['account_sid'] ?? ''));
        $token = trim((string) ($data['api_key'] ?? ''));

        if (preg_match('/^AC[0-9a-f]{32}$/i', $sid) !== 1 || $token === '') {
            throw new InvalidArgumentException('The Twilio connection needs the Account SID (it starts with AC) and the Auth Token. Edit it in Apps.');
        }

        return [$sid, $token];
    }

    /**
     * Call Twilio and return the decoded body (null for "no content").
     *
     * `$path` is under the account (`/Messages.json`) for the `api` service and
     * absolute for the others. A null param is left out; an empty string is sent
     * (it clears a setting).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @param  'api'|'lookups'|'verify'  $service
     *
     * @throws RuntimeException
     */
    protected function twilio(Run $run, array $config, string $method, string $path, array $params = [], string $service = 'api'): mixed
    {
        [$sid, $token] = $this->account($run, $config);
        $params = array_filter($params, fn (mixed $value): bool => $value !== null);
        $url = self::HOSTS[$service].($service === 'api' ? "/2010-04-01/Accounts/{$sid}{$path}" : $path);
        $request = Http::withBasicAuth($sid, $token)->acceptJson()->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $params === [] ? $request->get($url) : $request->get($url, $params),
            'DELETE' => $request->delete($url),
            default => $request->withBody($this->formBody($params), 'application/x-www-form-urlencoded')->post($url),
        };

        return $this->decode($response);
    }

    /**
     * A form body where a list repeats its key (`MediaUrl=a&MediaUrl=b`), as Twilio reads it.
     *
     * @param  array<string, mixed>  $params
     */
    protected function formBody(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            foreach ((array) $value as $item) {
                $pairs[] = rawurlencode((string) $key).'='.rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
            }
        }

        return implode('&', $pairs);
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
            $response->status() === 401 => ' Check the Account SID and Auth Token on the Twilio connection.',
            $response->status() === 429 => ' Twilio is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Twilio error'.($code ? " {$code}" : '').": {$message}.{$hint}");
    }

    /**
     * Read a list page by page until `$limit` records are in or it ends.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $params, int $limit): array
    {
        $records = [];
        $next = null;

        do {
            $page = $next === null
                ? $this->twilio($run, $config, 'GET', $path, [...$params, 'PageSize' => min(self::PAGE_SIZE, $limit - count($records))])
                : $this->twilio($run, $config, 'GET', (string) preg_replace('~^/2010-04-01/Accounts/[^/]+~', '', (string) $next));

            foreach ((array) data_get($page, $key, []) as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }

            $next = data_get($page, 'next_page_uri');
        } while (is_string($next) && $next !== '' && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * A phone number in international format, optionally with a channel prefix kept.
     *
     * @throws InvalidArgumentException
     */
    protected function phone(mixed $value, string $label, bool $allowChannel = false): string
    {
        $text = trim((string) $value);
        $channel = '';

        if (preg_match('/^(whatsapp|messenger):/i', $text, $match) === 1) {
            $channel = strtolower($match[1]).':';
            $text = trim(substr($text, strlen($match[0])));
        }

        $number = (string) preg_replace('/[\s().-]+/', '', $text);

        if ($text === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        if (preg_match('/^\+[1-9]\d{6,14}$/', $number) !== 1) {
            throw new InvalidArgumentException("\"{$text}\" is not a phone number Twilio can use for {$label}. Use international format with a leading +, e.g. +14155550123.");
        }

        return ($allowChannel ? $channel : '').$number;
    }

    /**
     * A required text setting.
     *
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
     * A Twilio SID of the kind that starts with `$prefix`.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function sid(array $config, string $key, string $label, string $prefix): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^'.preg_quote($prefix, '/').'[0-9a-f]{32}$/i', $value) === 1
            ? $value
            : throw new InvalidArgumentException("\"{$value}\" is not a {$label} (it starts with {$prefix} and has 34 characters).");
    }

    /**
     * The From number or Messaging Service of an outgoing message: exactly one.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: ?string, 1: ?string}
     *
     * @throws InvalidArgumentException
     */
    protected function sender(array $config, bool $whatsapp = false): array
    {
        $service = trim((string) ($config['messaging_service_sid'] ?? ''));
        $from = trim((string) ($config['from'] ?? ''));

        if ($service !== '' && $from !== '') {
            throw new InvalidArgumentException('Use either a From number or a Messaging Service, not both.');
        }

        if ($service === '' && $from === '') {
            throw new InvalidArgumentException('Choose a From number or a Messaging Service to send from.');
        }

        if ($service !== '') {
            return [null, $this->sid($config, 'messaging_service_sid', 'Messaging Service SID', 'MG')];
        }

        $number = $this->phone($from, 'From', allowChannel: true);

        return [$whatsapp ? 'whatsapp:'.preg_replace('/^whatsapp:/', '', $number) : $number, null];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function mediaUrls(array $config): array
    {
        $urls = $this->csv($config['media_urls'] ?? null);

        if (count($urls) > 10) {
            throw new InvalidArgumentException('A message takes at most 10 media files.');
        }

        foreach ($urls as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                throw new InvalidArgumentException("The media link \"{$url}\" must start with http:// or https://.");
            }
        }

        return $urls;
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
     * Twilio's date (RFC 2822) as ISO text.
     */
    protected function date(mixed $value): ?string
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
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function shapeMessage(array $message): array
    {
        return [
            'sid' => $message['sid'] ?? null,
            'status' => $message['status'] ?? null,
            'direction' => $message['direction'] ?? null,
            'from' => $message['from'] ?? null,
            'to' => $message['to'] ?? null,
            'body' => $message['body'] ?? null,
            'num_segments' => isset($message['num_segments']) ? (int) $message['num_segments'] : null,
            'num_media' => isset($message['num_media']) ? (int) $message['num_media'] : 0,
            'price' => isset($message['price']) ? (float) $message['price'] : null,
            'price_unit' => $message['price_unit'] ?? null,
            'error_code' => $message['error_code'] ?? null,
            'error_message' => $message['error_message'] ?? null,
            'messaging_service_sid' => $message['messaging_service_sid'] ?? null,
            'channel' => str_starts_with((string) ($message['to'] ?? $message['from'] ?? ''), 'whatsapp:') ? 'whatsapp' : 'sms',
            'date_created' => $this->date($message['date_created'] ?? null),
            'date_sent' => $this->date($message['date_sent'] ?? null),
            'date_updated' => $this->date($message['date_updated'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $call
     * @return array<string, mixed>
     */
    protected function shapeCall(array $call): array
    {
        return [
            'sid' => $call['sid'] ?? null,
            'status' => $call['status'] ?? null,
            'direction' => $call['direction'] ?? null,
            'from' => $call['from'] ?? null,
            'to' => $call['to'] ?? null,
            'duration_seconds' => isset($call['duration']) ? (int) $call['duration'] : null,
            'price' => isset($call['price']) ? (float) $call['price'] : null,
            'price_unit' => $call['price_unit'] ?? null,
            'answered_by' => $call['answered_by'] ?? null,
            'caller_name' => $call['caller_name'] ?? null,
            'forwarded_from' => $call['forwarded_from'] ?? null,
            'start_time' => $this->date($call['start_time'] ?? null),
            'end_time' => $this->date($call['end_time'] ?? null),
            'date_created' => $this->date($call['date_created'] ?? null),
        ];
    }

    /**
     * The sender settings every outgoing message node shares: a number the
     * account owns, or a Messaging Service that picks one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function senderFields(): array
    {
        return [
            'from' => Field::dynamic('From number', 'twilio.numbers', 'A Twilio number of the account. Leave empty if you use a Messaging Service.', '+14155550123'),
            'messaging_service_sid' => Field::advanced(Field::dynamic('Messaging Service', 'twilio.messaging_services', 'Send through a Messaging Service (it picks the number) instead of a From number.', 'MG...')),
        ];
    }
}
