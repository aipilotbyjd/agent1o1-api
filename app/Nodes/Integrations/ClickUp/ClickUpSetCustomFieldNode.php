<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ClickUpSetCustomFieldNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_set_custom_field';
    }

    public function name(): string
    {
        return 'ClickUp: Set Custom Field';
    }

    public function description(): string
    {
        return 'Sets a custom field on a task. Text and numbers go in as they are; for a dropdown give the option\'s id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['task_id', 'field_id', 'value'],
            'properties' => [
                ...$this->credentialFields(),
                'task_id' => Field::text('Task', 'The task id, from Create Task or List Tasks.', '86abc123'),
                'field_id' => Field::text('Field id', 'From List Custom Fields.'),
                'value' => Field::text('Value'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->taskId($config);
        $field = $this->requiredString($config, 'field_id', 'The field');

        if (preg_match('/^[A-Za-z0-9-]+$/', $field) !== 1) {
            throw new InvalidArgumentException('The field is not a ClickUp field id: copy it from List Custom Fields.');
        }

        $value = $config['value'] ?? null;

        if ($value === null || $value === '') {
            throw new InvalidArgumentException('The value is required.');
        }

        $this->clickup($run, $config, 'POST', "/task/{$id}/field/{$field}", [], ['value' => is_numeric($value) ? $value + 0 : $value]);

        return ['set' => true, 'task_id' => $id, 'field_id' => $field];
    }
}
