<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ResendSendEmailNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_send_email';
    }

    public function name(): string
    {
        return 'Resend: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email to up to 50 people. Can be scheduled, tagged and carry attachments from links.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sender_email', 'to', 'subject'],
            'properties' => [
                ...$this->credentialFields(),
                'sender_email' => Field::text('From address', 'On a domain you verified in Resend.', 'hello@acme.com'),
                'sender_name' => Field::advanced(Field::text('From name')),

                'to' => Field::emails('To'),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'reply_to' => Field::advanced(Field::emails('Reply-to')),
                'subject' => Field::text('Subject'),
                'html' => Field::textarea('Message (HTML)'),
                'text' => Field::textarea('Message (plain text)', 'The plain version; sent along with the HTML, or alone.'),
                'attachments' => Field::advanced(Field::list('Attachments (links)', 'One link per row; each file is fetched by Resend.', 'https://example.com/invoice.pdf')),
                'tags' => Field::advanced(Field::json('Tags (JSON)', 'Name and value pairs for filtering, e.g. {"campaign": "welcome"}.')),
                'headers' => Field::advanced(Field::json('Headers (JSON)', 'Extra headers, e.g. {"X-Entity-Ref-ID": "123"}.')),
                'scheduled_at' => Field::advanced(Field::datetime('Send at', 'Up to 30 days ahead. Leave empty to send now.')),
                'idempotency_key' => Field::advanced(Field::text('Idempotency key', 'Sending twice with the same key sends once. Use an order id to make a retry safe.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->emails($config, 'to');
        $html = trim((string) ($config['html'] ?? ''));
        $text = trim((string) ($config['text'] ?? ''));

        if ($to === [] || count($to) > 50) {
            throw new InvalidArgumentException('Give between 1 and 50 recipients.');
        }

        if ($html === '' && $text === '') {
            throw new InvalidArgumentException('Give the message as HTML or plain text.');
        }

        $when = null;

        if (filled($config['scheduled_at'] ?? null)) {
            $at = Carbon::parse((string) $config['scheduled_at'])->utc();

            if ($at->lte(now()) || $at->gt(now()->addDays(30))) {
                throw new InvalidArgumentException('"Send at" must be in the future, within 30 days.');
            }

            $when = $at->toIso8601String();
        }

        $tags = [];

        foreach ((array) ($this->decoded($config['tags'] ?? null, 'The tags') ?? []) as $name => $value) {
            $tags[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        $attachments = array_values(array_filter(array_map(fn (mixed $link): string => trim((string) $link), (array) ($config['attachments'] ?? []))));
        $key = trim((string) ($config['idempotency_key'] ?? ''));

        $sent = (array) $this->resend($run, $config, 'POST', '/emails', [
            'from' => $this->sender($config),
            'to' => $to,
            'cc' => $this->emails($config, 'cc') ?: null,
            'bcc' => $this->emails($config, 'bcc') ?: null,
            'reply_to' => $this->emails($config, 'reply_to') ?: null,
            'subject' => $this->oneLine($this->requiredString($config, 'subject', 'The subject')),
            'html' => $html === '' ? null : $html,
            'text' => $text === '' ? null : $text,
            'attachments' => $attachments === [] ? null : array_map(fn (string $link): array => ['path' => $link, 'filename' => basename((string) parse_url($link, PHP_URL_PATH)) ?: 'attachment'], $attachments),
            'tags' => $tags === [] ? null : $tags,
            'headers' => $this->decoded($config['headers'] ?? null, 'The headers'),
            'scheduled_at' => $when,
        ], headers: $key === '' ? [] : ['Idempotency-Key' => $key]);

        return ['sent' => true, 'id' => $sent['id'] ?? null, 'to' => $to, 'scheduled' => $when !== null];
    }
}
