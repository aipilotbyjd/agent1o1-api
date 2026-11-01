<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpGetMemberActivityNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_member_activity';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Member Activity';
    }

    public function description(): string
    {
        return 'Lists what a person did with your emails: opens, clicks, bounces and unsubscribes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id', 'email'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->audienceField(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $activity = array_values(array_filter((array) data_get((array) $this->mailchimp($run, $config, 'GET', $this->memberPath($config).'/activity-feed', query: ['count' => $this->limit($config, 50, 500)]), 'activity', []), 'is_array'));

        return ['activity' => array_map(fn (array $item): array => ['type' => $item['activity_type'] ?? null, 'created_at' => $item['created_at_timestamp'] ?? null, 'title' => $item['title'] ?? null, 'campaign_id' => $item['campaign_id'] ?? null, 'link_url' => $item['link_url'] ?? null], $activity), 'count' => count($activity)];
    }
}
