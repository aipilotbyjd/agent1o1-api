<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TrelloListBoardsNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_list_boards';
    }

    public function name(): string
    {
        return 'Trello: List Boards';
    }

    public function description(): string
    {
        return 'Lists the boards the member can see.';
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
                'include_closed' => Field::advanced(Field::boolean('Include closed boards', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $boards = $this->trello($run, $config, 'GET', '/members/me/boards', ['filter' => ($config['include_closed'] ?? false) ? 'all' : 'open', 'fields' => 'id,name,desc,url,closed,idOrganization']);

        return ['boards' => array_map(fn (array $board): array => ['id' => $board['id'] ?? null, 'name' => $board['name'] ?? null, 'description' => $board['desc'] ?? null, 'url' => $board['url'] ?? null, 'closed' => (bool) ($board['closed'] ?? false), 'workspace_id' => $board['idOrganization'] ?? null], array_values(array_filter($boards, 'is_array'))), 'count' => count($boards)];
    }
}
