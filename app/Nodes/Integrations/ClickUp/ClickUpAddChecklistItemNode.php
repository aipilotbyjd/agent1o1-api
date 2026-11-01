<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ClickUpAddChecklistItemNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_add_checklist_item';
    }

    public function name(): string
    {
        return 'ClickUp: Add Checklist Item';
    }

    public function description(): string
    {
        return 'Adds an item to a checklist.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['checklist_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'checklist_id' => Field::text('Checklist id', 'From Add Checklist to Task or Get Task.'),
                'name' => Field::text('Item'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $checklist = $this->requiredString($config, 'checklist_id', 'The checklist');

        if (preg_match('/^[A-Za-z0-9-]+$/', $checklist) !== 1) {
            throw new InvalidArgumentException('The checklist is not a ClickUp checklist id.');
        }

        $result = (array) ($this->clickup($run, $config, 'POST', "/checklist/{$checklist}/checklist_item", [], ['name' => $this->requiredString($config, 'name', 'The item')])['checklist'] ?? []);

        return ['checklist_id' => $checklist, 'items' => count((array) ($result['items'] ?? []))];
    }
}
