<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SendGridSendEmailNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_send_email';
    }

    public function name(): string
    {
        return 'SendGrid: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email, written here or from a dynamic template, to one or many people. Can be scheduled up to 72 hours ahead.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sender_email', 'to'],
            'properties' => [
                ...$this->credentialFields(),
                'sender_email' => Field::dynamic('From', 'sendgrid.senders', 'A verified sender.', 'hello@acme.com', allowCustom: true),
                'sender_name' => Field::advanced(Field::text('From name')),
                'to' => Field::emails('To'),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'reply_to' => Field::advanced(Field::text('Reply-to address')),
                'subject' => Field::text('Subject', 'Leave empty if the template sets it.'),
                'html' => Field::textarea('Message (HTML)'),
                'text' => Field::textarea('Message (plain text)', 'The plain version; sent along with the HTML, or alone.'),
                'template_id' => Field::advanced(Field::dynamic('Dynamic template', 'sendgrid.templates', 'Fill a SendGrid template instead of writing the message.', 'Welcome email')),
                'template_data' => Field::advanced(Field::json('Template data (JSON)', 'Values for the template, e.g. {"name": "Ada"}.')),
                'one_each' => Field::advanced(Field::boolean('Send each person their own copy', 'Recipients do not see each other. Up to 1000 people.', false)),
                'categories' => Field::advanced(Field::text('Categories', 'Comma-separated tags for the stats (up to 10).')),
                'send_at' => Field::advanced(Field::datetime('Send at', 'Up to 72 hours from now. Leave empty to send now.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->emails($config, 'to');
        $cc = $this->emails($config, 'cc');
        $bcc = $this->emails($config, 'bcc');
        $template = trim((string) ($config['template_id'] ?? ''));
        $subject = $this->oneLine($config['subject'] ?? '');
        $html = trim((string) ($config['html'] ?? ''));
        $text = trim((string) ($config['text'] ?? ''));

        if ($to === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        if ($template === '' && ($subject === '' || ($html === '' && $text === ''))) {
            throw new InvalidArgumentException('Give a subject and some content or a template.');
        }

        if (count($to) > 1000) {
            throw new InvalidArgumentException('SendGrid sends to up to 1000 people at a time.');
        }

        $sendAt = null;

        if (filled($config['send_at'] ?? null)) {
            $when = Carbon::parse((string) $config['send_at'])->utc();

            if ($when->lte(now()) || $when->gt(now()->addHours(72))) {
                throw new InvalidArgumentException('"Send at" must be in the future, within 72 hours.');
            }

            $sendAt = $when->timestamp;
        }

        $data = $this->decoded($config['template_data'] ?? null, 'The template data');
        $shared = [
            'cc' => $cc === [] ? null : array_map(fn (string $email): array => ['email' => $email], $cc),
            'bcc' => $bcc === [] ? null : array_map(fn (string $email): array => ['email' => $email], $bcc),
            'dynamic_template_data' => $template !== '' ? $data : null,
        ];
        $groups = ($config['one_each'] ?? false) ? array_map(fn (string $email): array => [$email], $to) : [$to];
        $personalizations = array_map(fn (array $group): array => $this->withoutNulls([
            'to' => array_map(fn (string $email): array => ['email' => $email], $group),
            ...$shared,
        ]), $groups);

        $content = [];

        if ($text !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }

        if ($html !== '') {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        $categories = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) ($config['categories'] ?? ''))))), 0, 10);
        $replyTo = filled($config['reply_to'] ?? null) ? $this->email($config, 'reply_to') : null;

        $response = $this->sendgridResponse($run, $config, 'POST', '/mail/send', $this->withoutNulls([
            'personalizations' => $personalizations,
            'from' => $this->withoutNulls(['email' => $this->email($config, 'sender_email'), 'name' => $this->oneLine($config['sender_name'] ?? '') ?: null]),
            'reply_to' => $replyTo === null ? null : ['email' => $replyTo],
            'subject' => $subject === '' ? null : $subject,
            'content' => $content === [] ? null : $content,
            'template_id' => $template === '' ? null : $template,
            'categories' => $categories === [] ? null : $categories,
            'send_at' => $sendAt,
        ]));
        $this->assertOk($response);

        return ['sent' => true, 'message_id' => $response->header('X-Message-Id') ?: null, 'to' => $to, 'scheduled' => $sendAt !== null];
    }
}
