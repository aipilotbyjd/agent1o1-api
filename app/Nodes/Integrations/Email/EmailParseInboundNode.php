<?php

namespace App\Nodes\Integrations\Email;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Turns the request an inbound-email service posts (Mailgun routes, SendGrid
 * Inbound Parse, Postmark inbound, or anything that names the usual fields)
 * into one shape, so a flow reads `from_email` and `text` whichever service
 * delivers the mail.
 *
 * This is how a flow starts from received email: point the service at a
 * Webhook trigger's URL, then put this step after it. Reading a mailbox over
 * IMAP is not something this app can do.
 */
class EmailParseInboundNode implements DeclaresEffect, NodeContract
{
    public function type(): string
    {
        return 'email_parse_inbound';
    }

    public function category(): string
    {
        return 'smtp';
    }

    public function name(): string
    {
        return 'Email: Parse Inbound Email';
    }

    public function description(): string
    {
        return 'Reads the request an inbound email service (Mailgun, SendGrid, Postmark) sends to a Webhook trigger and returns the sender, recipients, subject, text, HTML and attachments in one shape. Use it right after a Webhook trigger to start a flow from received email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['payload'],
            'properties' => [
                'payload' => Field::json('Request body', 'What the Webhook trigger received: map its output here.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $payload = $config['payload'] ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('The request body is empty: map the Webhook trigger\'s output into "Request body".');
        }

        // Some triggers wrap the body one level down.
        foreach (['body', 'payload', 'data'] as $wrapper) {
            if (is_array($payload[$wrapper] ?? null) && ! isset($payload['from']) && ! isset($payload['From']) && ! isset($payload['sender']) && ! isset($payload['recipient'])) {
                $payload = $payload[$wrapper];
            }
        }

        return match (true) {
            isset($payload['FromFull']) || isset($payload['TextBody']) || isset($payload['MessageID']) => $this->postmark($payload),
            isset($payload['body-plain']) || isset($payload['recipient']) || isset($payload['stripped-text']) => $this->mailgun($payload),
            isset($payload['envelope']) || isset($payload['charsets']) || isset($payload['spam_score']) => $this->sendgrid($payload),
            default => $this->generic($payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postmark(array $payload): array
    {
        return $this->shape('postmark', [
            'from' => $payload['FromFull']['Email'] ?? $payload['From'] ?? null,
            'from_name' => $payload['FromFull']['Name'] ?? $payload['FromName'] ?? null,
            'to' => array_column((array) ($payload['ToFull'] ?? []), 'Email') ?: ($payload['To'] ?? null),
            'cc' => array_column((array) ($payload['CcFull'] ?? []), 'Email') ?: ($payload['Cc'] ?? null),
            'subject' => $payload['Subject'] ?? null,
            'text' => $payload['TextBody'] ?? null,
            'html' => $payload['HtmlBody'] ?? null,
            'reply_text' => $payload['StrippedTextReply'] ?? null,
            'message_id' => $payload['MessageID'] ?? null,
            'date' => $payload['Date'] ?? null,
            'attachments' => array_map(fn (array $file): array => [
                'name' => $file['Name'] ?? null,
                'content_type' => $file['ContentType'] ?? null,
                'size' => $file['ContentLength'] ?? null,
            ], array_filter((array) ($payload['Attachments'] ?? []), 'is_array')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mailgun(array $payload): array
    {
        $count = (int) ($payload['attachment-count'] ?? 0);

        return $this->shape('mailgun', [
            'from' => $payload['sender'] ?? $payload['from'] ?? null,
            'to' => $payload['recipient'] ?? $payload['To'] ?? $payload['to'] ?? null,
            'cc' => $payload['Cc'] ?? $payload['cc'] ?? null,
            'subject' => $payload['subject'] ?? $payload['Subject'] ?? null,
            'text' => $payload['body-plain'] ?? null,
            'html' => $payload['body-html'] ?? null,
            'reply_text' => $payload['stripped-text'] ?? null,
            'message_id' => $payload['Message-Id'] ?? $payload['message-id'] ?? null,
            'date' => isset($payload['timestamp']) ? date(DATE_ATOM, (int) $payload['timestamp']) : ($payload['Date'] ?? null),
            'attachments' => $count > 0 ? array_map(fn (int $n): array => ['name' => "attachment-{$n}"], range(1, $count)) : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendgrid(array $payload): array
    {
        $envelope = is_string($payload['envelope'] ?? null) ? (array) json_decode($payload['envelope'], true) : (array) ($payload['envelope'] ?? []);

        return $this->shape('sendgrid', [
            'from' => $payload['from'] ?? $envelope['from'] ?? null,
            'to' => $payload['to'] ?? $envelope['to'] ?? null,
            'cc' => $payload['cc'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'text' => $payload['text'] ?? null,
            'html' => $payload['html'] ?? null,
            'reply_text' => null,
            'message_id' => $this->headerValue((string) ($payload['headers'] ?? ''), 'Message-ID'),
            'date' => $this->headerValue((string) ($payload['headers'] ?? ''), 'Date'),
            'attachments' => ((int) ($payload['attachments'] ?? 0)) > 0 ? array_map(fn (int $n): array => ['name' => "attachment{$n}"], range(1, (int) $payload['attachments'])) : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function generic(array $payload): array
    {
        $pick = fn (string ...$keys): mixed => collect($keys)->map(fn (string $key): mixed => $payload[$key] ?? null)->first(fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->shape('generic', [
            'from' => $pick('from', 'From', 'sender', 'from_email'),
            'to' => $pick('to', 'To', 'recipient', 'recipients'),
            'cc' => $pick('cc', 'Cc'),
            'subject' => $pick('subject', 'Subject'),
            'text' => $pick('text', 'body', 'plain', 'body_text', 'TextBody'),
            'html' => $pick('html', 'body_html', 'HtmlBody'),
            'reply_text' => $pick('reply_text', 'stripped_text'),
            'message_id' => $pick('message_id', 'Message-ID', 'MessageID'),
            'date' => $pick('date', 'Date', 'timestamp'),
            'attachments' => array_values(array_filter((array) ($payload['attachments'] ?? []), 'is_array')),
        ]);
    }

    /**
     * One message, whichever service sent it.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function shape(string $provider, array $parsed): array
    {
        [$fromEmail, $fromName] = $this->person((string) ($parsed['from'] ?? ''), $parsed['from_name'] ?? null);
        $text = $parsed['text'] ?? null;
        $html = $parsed['html'] ?? null;

        return [
            'provider' => $provider,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to' => $this->addressList($parsed['to'] ?? null),
            'cc' => $this->addressList($parsed['cc'] ?? null),
            'subject' => $parsed['subject'] ?? null,
            'text' => is_string($text) ? $text : null,
            'html' => is_string($html) ? $html : null,
            // What the sender wrote, without the quoted thread, when the service works it out.
            'reply_text' => is_string($parsed['reply_text'] ?? null) && $parsed['reply_text'] !== '' ? $parsed['reply_text'] : (is_string($text) ? $text : null),
            'message_id' => is_string($parsed['message_id'] ?? null) ? trim($parsed['message_id'], '<>') : null,
            'date' => $parsed['date'] ?? null,
            'attachments' => array_values((array) ($parsed['attachments'] ?? [])),
            'attachment_count' => count((array) ($parsed['attachments'] ?? [])),
        ];
    }

    /**
     * "Ada Lovelace <ada@example.com>" as [address, name].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function person(string $value, mixed $knownName = null): array
    {
        $value = trim($value);

        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $value, $match) === 1) {
            $name = trim($match[1], " \"'");

            return [strtolower(trim($match[2])), $name !== '' ? $name : (is_string($knownName) && $knownName !== '' ? $knownName : null)];
        }

        return [$value !== '' ? strtolower($value) : null, is_string($knownName) && $knownName !== '' ? $knownName : null];
    }

    /**
     * Addresses from a header-style string or a list.
     *
     * @return list<string>
     */
    private function addressList(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value);

        return array_values(array_unique(array_filter(array_map(fn (mixed $part): string => (string) ($this->person((string) $part)[0] ?? ''), (array) $parts))));
    }

    private function headerValue(string $headers, string $name): ?string
    {
        return preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $headers, $match) === 1 ? trim($match[1]) : null;
    }
}
