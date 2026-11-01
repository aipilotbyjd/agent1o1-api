<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunListMembersNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_list_members';
    }

    public function name(): string
    {
        return 'Mailgun: List Members';
    }

    public function description(): string
    {
        return 'Lists the people on a mailing list, optionally only subscribed or unsubscribed.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list'],
            'properties' => [
                ...$this->credentialFields(),
                'list' => $this->listField(),
                'subscribed' => Field::advanced(Field::select('Show', ['any' => 'Everyone', 'yes' => 'Subscribed', 'no' => 'Unsubscribed'], null, 'any')),
                'limit' => Field::integer('Max results', null, 100, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filter = (string) ($config['subscribed'] ?? 'any');
        $members = $this->listAll($run, $config, '/v3/lists/'.$this->listAddress($config).'/members/pages', ['subscribed' => in_array($filter, ['yes', 'no'], true) ? $filter : null], $this->limit($config, 100, 10000), 100);

        return ['members' => array_map($this->shapeMember(...), $members), 'count' => count($members)];
    }
}
