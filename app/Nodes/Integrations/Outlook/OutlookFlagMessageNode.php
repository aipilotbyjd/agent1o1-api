<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookFlagMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_flag_message';
    }

    public function name(): string
    {
        return 'Outlook: Flag Message';
    }

    public function description(): string
    {
        return 'Flags a message for follow-up, marks the follow-up done, or clears the flag.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'flag' => Field::select('Flag', ['flagged' => 'Flag for follow-up', 'complete' => 'Mark complete', 'notFlagged' => 'Clear the flag'], null, 'flagged'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $flag = in_array($config['flag'] ?? 'flagged', ['flagged', 'complete', 'notFlagged'], true) ? ($config['flag'] ?? 'flagged') : 'flagged';

        $this->patch($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message'), $config, ['flag' => ['flagStatus' => $flag]]);

        return ['message_id' => trim((string) $config['message_id']), 'flag' => $flag];
    }
}
