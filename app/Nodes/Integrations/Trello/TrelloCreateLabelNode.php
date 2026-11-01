<?php

namespace App\Nodes\Integrations\Trello;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TrelloCreateLabelNode extends AbstractTrelloNode
{
    public function type(): string
    {
        return 'trello_create_label';
    }

    public function name(): string
    {
        return 'Trello: Create Label';
    }

    public function description(): string
    {
        return 'Adds a label to a board.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'name' => Field::text('Name', null, 'Urgent'),
                'color' => Field::select('Colour', ['yellow' => 'Yellow', 'purple' => 'Purple', 'blue' => 'Blue', 'red' => 'Red', 'green' => 'Green', 'orange' => 'Orange', 'black' => 'Black', 'sky' => 'Sky', 'pink' => 'Pink', 'lime' => 'Lime'], null, 'red'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $color = (string) ($config['color'] ?? 'red');

        if (! in_array($color, ['yellow', 'purple', 'blue', 'red', 'green', 'orange', 'black', 'sky', 'pink', 'lime'], true)) {
            throw new InvalidArgumentException("\"{$color}\" is not a Trello label colour.");
        }

        $label = $this->trello($run, $config, 'POST', '/labels', [], ['name' => $this->requiredString($config, 'name', 'The name'), 'color' => $color, 'idBoard' => $this->id($config, 'board_id', 'The board')]);

        return ['id' => $label['id'] ?? null, 'name' => $label['name'] ?? null, 'color' => $label['color'] ?? $color];
    }
}
