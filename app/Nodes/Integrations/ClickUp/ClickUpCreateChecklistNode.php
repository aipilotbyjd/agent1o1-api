<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ClickUpCreateChecklistNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_create_checklist';
    }

    public function name(): string
    {
        return 'ClickUp: Add Checklist to Task';
    }

    public function description(): string
    {
        return 'Adds a checklist to a task, with its first items.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'name' => Field::text('Name', null, 'Launch steps'),
                'items' => Field::list('Items', 'One item per row.', 'Write the email'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);
        $items = array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), (array) ($config['items'] ?? []))));
        $checklist = (array) ($this->clickup($run, $config, 'POST', "/task/{$id}/checklist", [], ['name' => $this->requiredString($config, 'name', 'The name')])['checklist'] ?? []);
        $checklistId = preg_match('/^[A-Za-z0-9-]+$/', (string) ($checklist['id'] ?? '')) === 1 ? (string) $checklist['id'] : null;

        foreach ($checklistId === null ? [] : $items as $item) {
            $this->clickup($run, $config, 'POST', "/checklist/{$checklistId}/checklist_item", [], ['name' => $item]);
        }

        return ['id' => $checklistId, 'name' => $checklist['name'] ?? null, 'task_id' => $id, 'items_added' => $checklistId === null ? 0 : count($items)];
    }
}
