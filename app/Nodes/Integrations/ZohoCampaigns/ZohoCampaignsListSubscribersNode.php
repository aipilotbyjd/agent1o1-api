<?php

namespace App\Nodes\Integrations\ZohoCampaigns;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoCampaignsListSubscribersNode extends AbstractZohoCampaignsNode
{
    public function type(): string
    {
        return 'zoho_campaigns_list_subscribers';
    }

    public function name(): string
    {
        return 'Zoho Campaigns: List Subscribers';
    }

    public function description(): string
    {
        return 'Lists the people on a mailing list, newest first, by status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_key'],
            'properties' => [
                ...$this->credentialFields(),
                'list_key' => $this->listField(),
                'status' => Field::advanced(Field::select('Status', ['active' => 'Subscribed', 'unsub' => 'Unsubscribed', 'bounce' => 'Bounced', 'spam' => 'Marked as spam'], null, 'active')),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = $this->key($config, 'list_key', 'The mailing list');
        $subscribers = $this->listAll($run, $config, 'getlistsubscribers', 'list_of_details', ['listkey' => $list, 'status' => in_array($config['status'] ?? null, ['active', 'unsub', 'bounce', 'spam'], true) ? $config['status'] : 'active', 'sort' => 'desc'], $this->limit($config, 50));

        return ['subscribers' => array_map(fn (array $subscriber): array => $this->shapeSubscriber($subscriber, $list), $subscribers), 'count' => count($subscribers)];
    }
}
