<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\BuildsMimeMessage;
use App\Nodes\Support\Field;

class GmailReplyToMessageNode extends AbstractGmailNode
{
    use BuildsMimeMessage;

    public function type(): string
    {
        return 'gmail_reply_to_message';
    }

    public function name(): string
    {
        return 'Gmail: Reply to Message';
    }

    public function description(): string
    {
        return 'Replies to an existing email thread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'message_id', 'thread_id', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'thread_id' => Field::dynamic('Thread', 'gmail.threads', 'The conversation the reply belongs to.'),
                'to' => Field::emails('To'),
                'cc' => Field::emails('Cc'),
                'subject' => Field::text('Subject', 'Sent as "Re: <subject>".'),
                'body' => Field::textarea('Body'),
                'is_html' => Field::boolean('Send as HTML', 'Treat the body as HTML instead of plain text.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $subject = trim((string) ($config['subject'] ?? ''));

        // In-Reply-To and References name the original by its Message-ID
        // header, not by Gmail's own id, so mail clients thread the reply.
        // The header names repeat in the query: Gmail ignores PHP's `name[0]=` form.
        $original = $this->get(
            $run,
            '/users/me/messages/'.rawurlencode((string) $config['message_id']).'?format=metadata&metadataHeaders=Message-ID&metadataHeaders=References',
            $config,
        );
        $messageId = $this->originalHeader($original, 'Message-ID');
        $references = trim($this->originalHeader($original, 'References').' '.$messageId);

        $raw = $this->rawMessage([
            'To' => $this->addressList($config['to']),
            'Cc' => $this->addressList($config['cc'] ?? ''),
            'Subject' => preg_match('/^re:/i', $subject) === 1 ? $subject : "Re: {$subject}",
            'In-Reply-To' => $messageId,
            'References' => $references,
        ], (string) $config['body'], (bool) ($config['is_html'] ?? false));

        return $this->post($run, '/users/me/messages/send', $config, [
            'raw' => $raw,
            'threadId' => $config['thread_id'],
        ]);
    }

    /**
     * A header's value on the original message, or an empty string.
     *
     * @param  array<string, mixed>  $message
     */
    private function originalHeader(array $message, string $name): string
    {
        foreach ((array) ($message['payload']['headers'] ?? []) as $header) {
            if (strcasecmp((string) ($header['name'] ?? ''), $name) === 0) {
                return trim((string) ($header['value'] ?? ''));
            }
        }

        return '';
    }
}
