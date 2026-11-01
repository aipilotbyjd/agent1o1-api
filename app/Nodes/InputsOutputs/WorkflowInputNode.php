<?php

namespace App\Nodes\InputsOutputs;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Enums\Workflows\InterfaceFieldType;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\WorkflowIo;
use RuntimeException;

/**
 * One value the workflow asks for when it runs. Every Input step becomes a
 * field on the run form (`WorkflowInterface`), and later steps read what
 * was entered as `{{ nodes.<key>.value }}`.
 */
class WorkflowInputNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    public const string TYPE = 'workflow_input';

    public function type(): string
    {
        return self::TYPE;
    }

    public function category(): string
    {
        return NodeCategory::InputsOutputs->value;
    }

    public function name(): string
    {
        return 'Input';
    }

    public function icon(): string
    {
        return 'login-square-01';
    }

    public function description(): string
    {
        return 'Asks for a value when the workflow runs and passes it to later steps.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => Field::text('Name', 'The field on the run form, and the key an API call sends it under. Letters, numbers and underscores.', 'topic'),
                'input_type' => Field::select('Type', [
                    InterfaceFieldType::String->value => 'Short text',
                    InterfaceFieldType::Text->value => 'Long text',
                    InterfaceFieldType::Number->value => 'Number',
                    InterfaceFieldType::Boolean->value => 'Yes / No',
                    InterfaceFieldType::Select->value => 'Dropdown',
                    InterfaceFieldType::Json->value => 'JSON',
                ], default: InterfaceFieldType::String->value),
                'required' => Field::boolean('Required', 'A run can\'t start without it.'),
                'default' => Field::text('Default value', 'Used when a run doesn\'t provide this input.'),
                'options' => Field::advanced(Field::list('Dropdown choices', 'The choices offered when the type is Dropdown.')),
                'label' => Field::advanced(Field::text('Label', 'Shown on the run form instead of the name.')),
                'description' => Field::advanced(Field::text('Help text', 'Shown under the field on the run form.')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => ['title' => 'Value', 'description' => 'What was entered for this input.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $value = WorkflowIo::inputValue($config, $context['input'] ?? []);

        if ($value === null && ($config['required'] ?? false)) {
            throw new RuntimeException("The run is missing the required input \"{$config['name']}\".");
        }

        return ['value' => $value];
    }
}
