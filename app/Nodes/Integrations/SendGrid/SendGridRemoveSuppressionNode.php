<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SendGridRemoveSuppressionNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_remove_suppression';
    }

    public function name(): string
    {
        return 'SendGrid: Remove from Suppression List';
    }

    public function description(): string
    {
        return 'Takes an address off a suppression list so SendGrid will try to send to it again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'group'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'group' => Field::select('List', ['bounces' => 'Bounces', 'blocks' => 'Blocks', 'spam_reports' => 'Spam reports', 'invalid_emails' => 'Invalid emails', 'unsubscribes' => 'Global unsubscribes'], null, 'bounces'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $email = $this->email($config);
        $group = (string) ($config['group'] ?? 'bounces');

        if (! in_array($group, ['bounces', 'blocks', 'spam_reports', 'invalid_emails', 'unsubscribes'], true)) {
            throw new InvalidArgumentException("\"{$group}\" is not a suppression list.");
        }

        $path = $group === 'unsubscribes' ? '/asm/suppressions/global/' : '/suppression/'.$group.'/';
        $this->sendgrid($run, $config, 'DELETE', $path.rawurlencode($email));

        return ['removed' => true, 'email' => $email, 'group' => $group];
    }
}
