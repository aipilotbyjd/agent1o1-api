<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridListListsNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_list_lists';
    }

    public function name(): string
    {
        return 'SendGrid: List Lists';
    }

    public function description(): string
    {
        return 'Lists the marketing lists with their sizes.';
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
        $found = (array) $this->sendgrid($run, $config, 'GET', '/marketing/lists', query: ['page_size' => $this->limit($config, 100, 1000)]);
        $lists = array_map(fn (array $list): array => ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'contacts' => $list['contact_count'] ?? null], array_values(array_filter((array) ($found['result'] ?? []), 'is_array')));

        return ['lists' => $lists, 'count' => count($lists)];
    }
}
