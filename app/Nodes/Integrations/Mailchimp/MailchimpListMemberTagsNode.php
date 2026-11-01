<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpListMemberTagsNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_member_tags';
    }

    public function name(): string
    {
        return 'Mailchimp: List Member Tags';
    }

    public function description(): string
    {
        return 'Lists the tags a person has.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = array_values(array_filter((array) data_get((array) $this->mailchimp($run, $config, 'GET', $this->memberPath($config).'/tags', query: ['count' => 1000]), 'tags', []), 'is_array'));

        return ['tags' => array_map(fn (array $tag): string => (string) $tag['name'], $tags), 'count' => count($tags)];
    }
}
