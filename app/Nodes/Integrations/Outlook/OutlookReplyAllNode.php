<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class OutlookReplyAllNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_reply_all';
    }

    public function name(): string
    {
        return 'Outlook: Reply to All';
    }

    public function description(): string
    {
        return 'Replies to everyone on a message, keeping the thread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'comment'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'comment' => Field::textarea('Reply'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $comment = trim((string) ($config['comment'] ?? ''));

        if ($comment === '') {
            throw new InvalidArgumentException('The reply is required.');
        }

        $this->post($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/replyAll', $config, ['comment' => $comment]);

        return ['replied' => true, 'message_id' => trim((string) $config['message_id'])];
    }
}
