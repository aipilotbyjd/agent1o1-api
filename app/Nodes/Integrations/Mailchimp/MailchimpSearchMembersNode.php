<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpSearchMembersNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_search_members';
    }

    public function name(): string
    {
        return 'Mailchimp: Search Members';
    }

    public function description(): string
    {
        return 'Finds people by part of an email address or name, in one audience or all of them.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search for', 'Part of an email address, first name or last name.', 'ada'),
                'list_id' => Field::advanced($this->audienceField('Only this audience. Leave empty to search all.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = (array) $this->mailchimp($run, $config, 'GET', '/search-members', query: [
            'query' => $this->requiredString($config, 'query', 'The search text'),
            'list_id' => filled($config['list_id'] ?? null) ? $this->listId($config) : null,
        ]);
        $members = array_values(array_filter((array) data_get($result, 'exact_matches.members', []), 'is_array'));
        $members = [...$members, ...array_values(array_filter((array) data_get($result, 'full_search.members', []), 'is_array'))];

        return ['members' => array_map($this->shapeMember(...), $members), 'count' => count($members)];
    }
}
