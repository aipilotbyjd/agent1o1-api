<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Names values for later steps: each variable becomes an output key, read as
 * `{{ nodes.<key>.<name> }}`. Values keep their type, so a variable set from
 * `{{ input.items }}` stays a list rather than turning into text.
 */
class SetVariableNode extends PureDataNode
{
    public function type(): string
    {
        return 'set_variable';
    }

    public function name(): string
    {
        return 'Set Variable';
    }

    public function icon(): string
    {
        return 'variable';
    }

    public function description(): string
    {
        return 'Names one or more values so later steps can use them, e.g. {{ nodes.vars.total }}. Values can be typed in or taken from earlier steps with {{ }}.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['variables'],
            'properties' => [
                'variables' => Field::keyValue('Variables', 'Each name becomes a field later steps can read. Names use letters, numbers and underscores.'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'description' => 'One field per variable you set.',
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $variables = $config['variables'] ?? [];

        if (! is_array($variables)) {
            throw new InvalidArgumentException('"variables" must be a list of name and value pairs.');
        }

        $output = [];

        foreach ($variables as $name => $value) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', (string) $name)) {
                throw new InvalidArgumentException("\"{$name}\" is not a valid variable name. Use letters, numbers and underscores, starting with a letter.");
            }

            $output[$name] = $value;
        }

        return $output;
    }
}
