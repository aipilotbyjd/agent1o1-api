<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunAddMemberNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_add_member';
    }

    public function name(): string
    {
        return 'Mailgun: Add or Update List Member';
    }

    public function description(): string
    {
        return 'Adds a person to a mailing list, or updates them if they are already on it. Safe to run twice.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
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
                'vars' => Field::advanced(Field::json('Custom data (JSON)', 'Stored with the member, e.g. {"plan": "pro"}.')),
                'subscribed' => Field::advanced(Field::select('Subscription', ['yes' => 'Subscribed', 'no' => 'Unsubscribed'], null, 'yes')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->listAddress($config);
        $vars = $this->decoded($config['vars'] ?? null, 'The custom data');

        $added = (array) $this->mailgun($run, $config, 'POST', "/v3/lists/{$list}/members", [
            'address' => $this->email($config),
            'name' => filled($config['name'] ?? null) ? $this->oneLine($config['name']) : null,
            'vars' => $vars === null ? null : json_encode($vars),
            'subscribed' => ($config['subscribed'] ?? 'yes') === 'no' ? 'no' : 'yes',
            'upsert' => 'yes',
        ]);

        return $this->shapeMember((array) ($added['member'] ?? ['address' => $this->email($config)]));
    }
}
