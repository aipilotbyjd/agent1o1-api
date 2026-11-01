<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloUpdateListNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_update_list';
    }

    public function name(): string
    {
        return 'Trello: Rename, Move or Archive List';
    }

    public function description(): string
    {
        return 'Renames a list, moves it, or archives it. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'list_id' => $this->listField(),
                'name' => Field::text('New name'),
                'position' => Field::advanced(Field::text('Position', 'top, bottom, or a number. Empty is the bottom.', 'top')),
                'archived' => Field::advanced(Field::select('Archive', ['unchanged' => 'Leave as it is', 'yes' => 'Archive', 'no' => 'Restore'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $archived = (string) ($config['archived'] ?? 'unchanged');
        $changes = ['name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null, 'pos' => $this->position($config), 'closed' => in_array($archived, ['yes', 'no'], true) ? $archived === 'yes' : null];

        if (array_filter($changes, fn (mixed $value): bool => $value !== null) === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $list = $this->trello($run, $config, 'PUT', '/lists/'.$this->id($config, 'list_id', 'The list'), $changes);

        return ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'archived' => (bool) ($list['closed'] ?? false)];
    }
}
