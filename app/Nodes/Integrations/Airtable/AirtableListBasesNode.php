<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AirtableListBasesNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_list_bases';
    }

    public function name(): string
    {
        return 'Airtable: List Bases';
    }

    public function description(): string
    {
        return 'Lists the bases the token can see.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bases = [];
        $offset = null;

        do {
            $page = $this->airtable($run, $config, 'GET', '/meta/bases', ['offset' => $offset]);
            array_push($bases, ...array_values(array_filter((array) ($page['bases'] ?? []), 'is_array')));
            $offset = $page['offset'] ?? null;
        } while ($offset !== null && count($bases) < 1000);

        return ['bases' => array_map(fn (array $base): array => ['id' => $base['id'] ?? null, 'name' => $base['name'] ?? null, 'permission' => $base['permissionLevel'] ?? null], $bases), 'count' => count($bases)];
    }
}
