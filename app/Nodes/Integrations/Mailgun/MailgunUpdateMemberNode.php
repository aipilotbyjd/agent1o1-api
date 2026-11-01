<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailgunUpdateMemberNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_update_member';
    }

    public function name(): string
    {
        return 'Mailgun: Update List Member';
    }

    public function description(): string
    {
        return 'Changes a list member\'s name, data or subscription. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list' => $this->listField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'name' => Field::text('Name'),
                'vars' => Field::advanced(Field::json('Custom data (JSON)')),
                'subscribed' => Field::advanced(Field::select('Subscription', ['unchanged' => 'Leave as it is', 'yes' => 'Subscribed', 'no' => 'Unsubscribed'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $vars = $this->decoded($config['vars'] ?? null, 'The custom data');
        $subscribed = (string) ($config['subscribed'] ?? 'unchanged');
        $form = [
            'name' => filled($config['name'] ?? null) ? $this->oneLine($config['name']) : null,
            'vars' => $vars === null ? null : json_encode($vars),
            'subscribed' => in_array($subscribed, ['yes', 'no'], true) ? $subscribed : null,
        ];

        if (array_filter($form, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $updated = (array) $this->mailgun($run, $config, 'PUT', '/v3/lists/'.$this->listAddress($config).'/members/'.rawurlencode($this->email($config)), $form);

        return $this->shapeMember((array) ($updated['member'] ?? ['address' => $this->email($config)]));
    }
}
