<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunListMailingListsNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_list_mailing_lists';
    }

    public function name(): string
    {
        return 'Mailgun: List Mailing Lists';
    }

    public function description(): string
    {
        return 'Lists the mailing lists with their sizes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $lists = $this->listAll($run, $config, '/v3/lists/pages', [], $this->limit($config, 100), 100);

        return ['lists' => array_map(fn (array $list): array => ['address' => $list['address'] ?? null, 'name' => $list['name'] ?? null, 'description' => $list['description'] ?? null, 'members' => $list['members_count'] ?? null, 'access_level' => $list['access_level'] ?? null, 'created_at' => $list['created_at'] ?? null], $lists), 'count' => count($lists)];
    }
}
