<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KitListSequencesNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_sequences';
    }

    public function name(): string
    {
        return 'Kit: List Sequences';
    }

    public function description(): string
    {
        return 'Lists the account\'s email sequences.';
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
        $sequences = $this->listAll($run, $config, '/sequences', 'sequences', [], 1000);

        return ['sequences' => array_map(fn (array $sequence): array => ['id' => $sequence['id'] ?? null, 'name' => $sequence['name'] ?? null, 'hold' => $sequence['hold'] ?? null, 'repeat' => $sequence['repeat'] ?? null, 'created_at' => $sequence['created_at'] ?? null], $sequences), 'count' => count($sequences)];
    }
}
