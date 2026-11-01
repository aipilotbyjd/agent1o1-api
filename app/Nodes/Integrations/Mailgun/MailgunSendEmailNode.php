<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use stdClass;

class MailgunSendEmailNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_send_email';
    }

    public function name(): string
    {
        return 'Mailgun: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email from your domain: written here or from a stored template, to one or many, tagged, tracked and optionally scheduled up to 7 days ahead.';
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
                'sender_email' => Field::text('From address', 'On your sending domain.', 'hello@mg.example.com'),
                'sender_name' => Field::advanced(Field::text('From name')),
                'to' => Field::emails('To', 'Up to 1000 addresses.'),
                'cc' => Field::advanced(Field::emails('Cc')),
                'bcc' => Field::advanced(Field::emails('Bcc')),
                'reply_to' => Field::advanced(Field::text('Reply-to address')),
                'subject' => Field::text('Subject', 'Leave empty if the template sets it.'),
                'html' => Field::textarea('Message (HTML)'),
                'text' => Field::textarea('Message (plain text)'),
                'template' => Field::advanced(Field::dynamic('Stored template', 'mailgun.templates', 'Fill a template stored in Mailgun instead of writing the message.', 'welcome', allowCustom: true)),
                'template_variables' => Field::advanced(Field::json('Template values (JSON)', 'What the template uses, e.g. {"name": "Ada"}.')),
                'one_each' => Field::advanced(Field::boolean('Send each person their own copy', 'Recipients do not see each other.', false)),
                'tags' => Field::advanced(Field::text('Tags', 'Comma-separated labels for the stats (up to 3).')),
                'track_opens' => Field::advanced(Field::select('Track opens', ['default' => 'Domain setting', 'yes' => 'Yes', 'no' => 'No'], null, 'default')),
                'track_clicks' => Field::advanced(Field::select('Track clicks', ['default' => 'Domain setting', 'yes' => 'Yes', 'no' => 'No', 'htmlonly' => 'HTML only'], null, 'default')),
                'variables' => Field::advanced(Field::json('Custom data (JSON)', 'Returned with every event for this email, e.g. {"order": "1001"}.')),
                'deliver_at' => Field::advanced(Field::datetime('Send at', 'Up to 7 days ahead. Leave empty to send now.')),
                'test_mode' => Field::advanced(Field::boolean('Test mode', 'Mailgun accepts it but delivers nothing.', false)),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $to = $this->emails($config, 'to');
        $template = trim((string) ($config['template'] ?? ''));
        $subject = $this->oneLine($config['subject'] ?? '');
        $html = trim((string) ($config['html'] ?? ''));
        $text = trim((string) ($config['text'] ?? ''));

        if ($to === [] || count($to) > 1000) {
            throw new InvalidArgumentException('Give between 1 and 1000 recipients.');
        }

        if ($template === '' && ($subject === '' || ($html === '' && $text === ''))) {
            throw new InvalidArgumentException('Give a subject and some content or a template.');
        }

        $deliverAt = null;

        if (filled($config['deliver_at'] ?? null)) {
            $at = Carbon::parse((string) $config['deliver_at'])->utc();

            if ($at->lte(now()) || $at->gt(now()->addDays(7))) {
                throw new InvalidArgumentException('"Send at" must be in the future, within 7 days.');
            }

            $deliverAt = $at->toRfc7231String();
        }

        $sender = $this->email($config, 'sender_email');
        $name = $this->oneLine(preg_replace('/[<>"]+/', ' ', (string) ($config['sender_name'] ?? '')));
        $tags = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) ($config['tags'] ?? ''))))), 0, 3);
        $custom = [];

        foreach ((array) ($this->decoded($config['variables'] ?? null, 'The custom data') ?? []) as $key => $value) {
            $custom['v:'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key)] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        $cc = $this->emails($config, 'cc');
        $bcc = $this->emails($config, 'bcc');
        $replyTo = filled($config['reply_to'] ?? null) ? $this->email($config, 'reply_to') : null;
        $templateValues = $this->decoded($config['template_variables'] ?? null, 'The template values');
        $track = fn (string $key, array $allowed): ?string => in_array($config[$key] ?? 'default', $allowed, true) ? $config[$key] : null;

        $sent = (array) $this->mailgun($run, $config, 'POST', "/v3/{$domain}/messages", [
            'from' => $name === '' ? $sender : "{$name} <{$sender}>",
            'to' => $to,
            'cc' => $cc === [] ? null : $cc,
            'bcc' => $bcc === [] ? null : $bcc,
            'subject' => $subject === '' ? null : $subject,
            'html' => $html === '' ? null : $html,
            'text' => $text === '' ? null : $text,
            'template' => $template === '' ? null : $template,
            'h:X-Mailgun-Variables' => $template !== '' && $templateValues !== null ? json_encode($templateValues) : null,
            'h:Reply-To' => $replyTo,
            'o:tag' => $tags === [] ? null : $tags,
            'o:tracking-opens' => $track('track_opens', ['yes', 'no']),
            'o:tracking-clicks' => $track('track_clicks', ['yes', 'no', 'htmlonly']),
            'o:deliverytime' => $deliverAt,
            'o:testmode' => ($config['test_mode'] ?? false) ? 'yes' : null,
            'recipient-variables' => ($config['one_each'] ?? false) ? json_encode(array_fill_keys($to, new stdClass)) : null,
            ...$custom,
        ]);

        return ['sent' => true, 'id' => $sent['id'] ?? null, 'message' => $sent['message'] ?? null, 'to' => $to, 'scheduled' => $deliverAt !== null];
    }
}
