<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BrevoSendEmailNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_send_email';
    }

    public function name(): string
    {
        return 'Brevo: Send Transactional Email';
    }

    public function description(): string
    {
        return 'Sends one email through Brevo\'s transactional service (receipts, confirmations, alerts): a message you write, or one of your templates with values filled in.';
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
                'sender_email' => Field::dynamic('From', 'brevo.senders', 'A sender you verified in Brevo.', 'hello@acme.com'),
                'sender_name' => Field::advanced(Field::text('From name', 'Shown as the sender. Defaults to the one Brevo has for it.')),

                'to' => Field::emails('To', 'Separate several addresses with commas.'),
                'subject' => Field::text('Subject', 'Needed unless the template has one.', 'Your order has shipped'),
                'html' => Field::textarea('Message (HTML)', 'The body. Leave empty if you use a template.'),
                'text' => Field::advanced(Field::textarea('Plain-text version')),
                'template_id' => Field::advanced(Field::dynamic('Template', 'brevo.templates', 'Send one of your templates instead of a message.', 'Order confirmation', type: 'integer')),
                'params' => Field::advanced(Field::json('Template values (JSON)', 'Values for {{params.name}} in the template, e.g. {"name": "Ada"}.')),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'reply_to' => Field::advanced(Field::text('Reply-to')),
                'tags' => Field::advanced(Field::list('Tags', 'To find these emails in Brevo\'s statistics.')),
                'send_at' => Field::advanced(Field::datetime('Send at', 'Leave empty to send now.')),
                'attachments' => Field::advanced(Field::list('Attachments', 'Links to files to attach.', 'https://example.com/invoice.pdf')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $template = is_numeric($config['template_id'] ?? null) ? (int) $config['template_id'] : null;
        $html = (string) ($config['html'] ?? '');
        $text = (string) ($config['text'] ?? '');

        if ($template === null && trim($html) === '' && trim($text) === '') {
            throw new InvalidArgumentException('The message needs some content or a template.');
        }

        $people = fn (mixed $value, string $label): array => array_map(fn (string $email): array => ['email' => $this->email(['e' => $email], 'e')], $this->addressList($value, $label));
        $to = $people($config['to'] ?? null, 'To');

        if ($to === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        $at = filled($config['send_at'] ?? null) ? Carbon::parse((string) $config['send_at'])->utc() : null;

        if ($at !== null && $at->isPast()) {
            throw new InvalidArgumentException('The send time must be in the future.');
        }

        $result = (array) $this->brevo($run, $config, 'POST', '/smtp/email', [
            'sender' => array_filter(['email' => $this->email($config, 'sender_email'), 'name' => filled($config['sender_name'] ?? null) ? (string) $config['sender_name'] : null], fn (mixed $value): bool => $value !== null),
            'to' => $to,
            'cc' => $people($config['cc'] ?? null, 'Cc') ?: null,
            'bcc' => $people($config['bcc'] ?? null, 'Bcc') ?: null,
            'replyTo' => filled($config['reply_to'] ?? null) ? ['email' => $this->email($config, 'reply_to')] : null,
            'subject' => filled($config['subject'] ?? null) ? $this->oneLine((string) $config['subject']) : null,
            'htmlContent' => $template === null && trim($html) !== '' ? $html : null,
            'textContent' => $template === null && trim($text) !== '' ? $text : null,
            'templateId' => $template,
            'params' => $this->decoded($config['params'] ?? null, 'The template values'),
            'tags' => array_values(array_filter(array_map('trim', is_array($config['tags'] ?? null) ? $config['tags'] : explode(',', (string) ($config['tags'] ?? ''))))) ?: null,
            'scheduledAt' => $at?->toIso8601String(),
            'attachment' => array_map(fn (string $url): array => ['url' => $url], array_values(array_filter(array_map('trim', is_array($config['attachments'] ?? null) ? $config['attachments'] : explode(',', (string) ($config['attachments'] ?? '')))))) ?: null,
        ]);

        return ['sent' => true, 'message_id' => $result['messageId'] ?? ($result['messageIds'][0] ?? null), 'to' => array_column($to, 'email'), 'scheduled' => $at !== null];
    }

    /**
     * Addresses from a comma-separated setting (or a list).
     *
     * @return list<string>
     */
    private function addressList(mixed $value, string $label): array
    {
        $parts = is_array($value) ? $value : preg_split('/[,;\n]+/', (string) $value);

        return array_values(array_unique(array_filter(array_map(fn (mixed $part): string => trim((string) $part), (array) $parts), fn (string $part): bool => $part !== '')));
    }

    /**
     * A header value on one line.
     */
    private function oneLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }
}
