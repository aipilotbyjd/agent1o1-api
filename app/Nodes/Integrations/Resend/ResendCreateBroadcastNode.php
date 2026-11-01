<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ResendCreateBroadcastNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_create_broadcast';
    }

    public function name(): string
    {
        return 'Resend: Create Broadcast';
    }

    public function description(): string
    {
        return 'Writes an email to a whole audience. Send it with Send Broadcast, or tick "Send now". Use {{{FIRST_NAME}}} and {{{RESEND_UNSUBSCRIBE_URL}}} in the message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'sender_email', 'subject'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
                'sender_email' => Field::text('From address', 'On a domain you verified in Resend.', 'hello@acme.com'),
                'sender_name' => Field::advanced(Field::text('From name')),

                'subject' => Field::text('Subject'),
                'html' => Field::textarea('Message (HTML)'),
                'text' => Field::textarea('Message (plain text)'),
                'name' => Field::advanced(Field::text('Internal name')),
                'reply_to' => Field::advanced(Field::text('Reply-to address')),
                'send_now' => Field::advanced(Field::boolean('Send now', 'Send it as soon as it is created.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $html = trim((string) ($config['html'] ?? ''));
        $text = trim((string) ($config['text'] ?? ''));

        if ($html === '' && $text === '') {
            throw new InvalidArgumentException('Give the message as HTML or plain text.');
        }

        $created = (array) $this->resend($run, $config, 'POST', '/broadcasts', [
            'audience_id' => $this->requiredString($config, 'audience_id', 'The audience'),
            'from' => $this->sender($config),
            'subject' => $this->oneLine($this->requiredString($config, 'subject', 'The subject')),
            'html' => $html === '' ? null : $html,
            'text' => $text === '' ? null : $text,
            'name' => filled($config['name'] ?? null) ? $this->oneLine($config['name']) : null,
            'reply_to' => filled($config['reply_to'] ?? null) ? $this->email($config, 'reply_to') : null,
        ]);
        $sent = false;

        if (($config['send_now'] ?? false) && isset($created['id'])) {
            $this->resend($run, $config, 'POST', '/broadcasts/'.rawurlencode((string) $created['id']).'/send', []);
            $sent = true;
        }

        return ['id' => $created['id'] ?? null, 'sent' => $sent];
    }
}
