<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoDeskListContactsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_list_contacts';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Contacts';
    }

    public function description(): string
    {
        return 'Lists the customer contacts, newest first.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $contacts = $this->listAll($run, $config, '/contacts', ['sortBy' => '-createdTime'], $this->limit($config, 50));

        return ['contacts' => array_map($this->shapeContact(...), $contacts), 'count' => count($contacts)];
    }
}
