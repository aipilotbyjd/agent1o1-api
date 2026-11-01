<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TrelloListLabelsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_labels';
    }

    public function name(): string
    {
        return 'Trello: List Labels';
    }

    public function description(): string
    {
        return 'Lists a board\'s labels.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $labels = $this->trello($run, $config, 'GET', '/boards/'.$this->id($config, 'board_id', 'The board').'/labels', ['limit' => 1000]);

        return ['labels' => array_map(fn (array $label): array => ['id' => $label['id'] ?? null, 'name' => $label['name'] ?? null, 'color' => $label['color'] ?? null], array_values(array_filter($labels, 'is_array'))), 'count' => count($labels)];
    }
}
