<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class OutlookForwardMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_forward_message';
    }

    public function name(): string
    {
        return 'Outlook: Forward Message';
    }

    public function description(): string
    {
        return 'Forwards a message, with its attachments, to other people with a note of your own.';
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
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'to' => Field::emails('Forward to'),
                'comment' => Field::textarea('Your note', 'Written above the forwarded message.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->recipients($config['to'] ?? '');

        if ($to === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        $this->post($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/forward', $config, ['comment' => (string) ($config['comment'] ?? ''), 'toRecipients' => $to]);

        return ['forwarded' => true, 'message_id' => trim((string) $config['message_id']), 'to' => array_column(array_column($to, 'emailAddress'), 'address')];
    }
}
