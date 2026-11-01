<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\BuildsMimeMessage;
use App\Nodes\Integrations\Gmail\Concerns\ShapesGmailMessages;
use App\Nodes\Support\Field;

class GmailForwardMessageNode extends AbstractGmailNode
{
    use BuildsMimeMessage;
    use ShapesGmailMessages;

    public function type(): string
    {
        return 'gmail_forward_message';
    }

    public function name(): string
    {
        return 'Gmail: Forward Message';
    }

    public function description(): string
    {
        return 'Forwards a message\'s text to other people with a note of your own. Attachments are not included.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'to'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'to' => Field::emails('Forward to'),
                'cc' => Field::advanced(Field::emails('Cc')),
                'comment' => Field::textarea('Your note', 'Written above the forwarded message.'),
                'is_html' => Field::advanced(Field::boolean('Send as HTML', 'Keep the original\'s formatting.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $original = $this->flatMessage($this->get($run, '/users/me/messages/'.$this->pathId($config, 'message_id', 'The message').'?format=full', $config));
        $subject = trim((string) ($original['subject'] ?? ''));
        $html = (bool) ($config['is_html'] ?? false);
        $note = trim((string) ($config['comment'] ?? ''));

        $header = [
            '---------- Forwarded message ---------',
            'From: '.($original['from'] ?? ''),
            'Date: '.($original['date'] ?? ''),
            'Subject: '.$subject,
            'To: '.($original['to'] ?? ''),
        ];

        $body = $html
            ? ($note === '' ? '' : '<p>'.nl2br(e($note)).'</p>').'<div>'.implode('<br>', array_map('e', $header)).'</div><br>'.($original['body_html'] !== '' ? $original['body_html'] : nl2br(e((string) $original['body_text'])))
            : ($note === '' ? '' : $note."\n\n").implode("\n", $header)."\n\n".$original['body_text'];

        $sent = $this->post($run, '/users/me/messages/send', $config, ['raw' => $this->rawMessage([
            'To' => $this->addressList($config['to']),
            'Cc' => $this->addressList($config['cc'] ?? ''),
            'Subject' => preg_match('/^fwd?:/i', $subject) === 1 ? $subject : "Fwd: {$subject}",
        ], $body, $html)]);

        return ['sent' => true, 'id' => $sent['id'] ?? null, 'thread_id' => $sent['threadId'] ?? null, 'forwarded_message_id' => $original['id']];
    }
}
