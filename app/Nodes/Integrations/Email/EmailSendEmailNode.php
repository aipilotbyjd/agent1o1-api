<?php

namespace App\Nodes\Integrations\Email;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class EmailSendEmailNode extends AbstractEmailNode
{
    public function type(): string
    {
        return 'email_send';
    }

    public function name(): string
    {
        return 'Email: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email through the connected SMTP server, with optional Cc, Bcc, Reply-To, HTML and attachments.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::emails('To', 'Who to send it to. Separate several addresses with commas.'),
                ...$this->messageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->addresses($config['to'] ?? null, 'To');

        if ($to === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        $account = $this->resolveCredentialData($run, $config);
        $message = $this->buildMessage($config, $account, $to);

        $this->deliver($account, $message);

        return [
            'sent' => true,
            'message_id' => $this->messageId($message),
            'from' => $this->mailer()->sender($account)['address'],
            'to' => $to,
            'cc' => $this->addresses($config['cc'] ?? null, 'Cc'),
            'bcc' => $this->addresses($config['bcc'] ?? null, 'Bcc'),
            'subject' => $message->getSubject(),
            'attachment_count' => count($message->getAttachments()),
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
