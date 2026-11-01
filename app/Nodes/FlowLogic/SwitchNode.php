<?php

namespace App\Nodes\FlowLogic;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\ConditionEvaluator;
use InvalidArgumentException;

/**
 * One value, many outcomes: compares a single value against a list of cases
 * and sends the run down the branch of the first case that matches, or down
 * `default`. Router is the one for "this, then that, then the other
 * condition"; Switch is the one for "what is the value of this?", such as a
 * status, a plan, a country or a category.
 *
 * `GraphAdvancer` matches each outgoing edge's condition to this node's
 * `result`, exactly as it does for Router.
 */
class SwitchNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    private const int MAX_CASES = 100;

    /**
     * How a value is compared to each case.
     *
     * @var array<string, string>
     */
    private const array MATCHES = [
        'equals' => 'Equals the case',
        'contains' => 'Contains the case',
        'starts_with' => 'Starts with the case',
        'ends_with' => 'Ends with the case',
        'in_list' => 'Is one of the case’s values (comma-separated)',
    ];

    public function type(): string
    {
        return 'switch';
    }

    public function category(): string
    {
        return NodeCategory::FlowLogic->value;
    }

    public function name(): string
    {
        return 'Switch';
    }

    public function icon(): string
    {
        return 'git-fork';
    }

    public function description(): string
    {
        return 'Looks at one value and sends the run down the branch of the first matching case, or down the default branch.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['value', 'cases'],
            'properties' => [
                'value' => Field::text('Value to check', 'Usually {{ ... }} from an earlier step, e.g. {{ nodes.lead.country }}.', '{{ input.status }}'),
                'cases' => Field::keyValue('Cases', 'The value to match on the left and the branch it sends the run down on the right. Checked top to bottom; the first match wins. Give two cases the same branch to send both the same way.'),
                'match' => Field::select('Match', self::MATCHES, null, 'equals'),
                'default_branch' => Field::text('If nothing matches', 'The branch to take when no case matches.', 'default'),
                'ignore_case' => Field::advanced(Field::boolean('Ignore capital letters', null, true)),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['title' => 'Branch', 'description' => 'The branch chosen. Connect an edge to each branch name.'],
                'matched' => ['title' => 'Matched', 'description' => 'True when a case matched, false when the default branch was taken.'],
                'case' => ['title' => 'Case', 'description' => 'The case that matched, or empty when none did.'],
                'value' => ['title' => 'Value', 'description' => 'The value that was checked.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $cases = $config['cases'] ?? [];

        if (is_string($cases)) {
            $cases = json_decode($cases, true);
        }

        if (! is_array($cases) || $cases === [] || array_is_list($cases)) {
            throw new InvalidArgumentException('Add at least one case: a value to match and the branch it leads to.');
        }

        if (count($cases) > self::MAX_CASES) {
            throw new InvalidArgumentException('A Switch can have at most '.self::MAX_CASES.' cases.');
        }

        $match = (string) ($config['match'] ?? 'equals');

        if (! isset(self::MATCHES[$match])) {
            throw new InvalidArgumentException("Unsupported match [{$match}].");
        }

        $value = $config['value'] ?? null;
        $ignoreCase = (bool) ($config['ignore_case'] ?? true);
        $default = trim((string) ($config['default_branch'] ?? '')) ?: 'default';
        $actual = is_scalar($value) || $value === null ? $value : json_encode($value);

        foreach ($cases as $case => $branch) {
            $branch = trim((string) $branch);

            if ($branch === '') {
                throw new InvalidArgumentException("The case “{$case}” has no branch to lead to.");
            }

            if ($this->matches($actual, (string) $case, $match, $ignoreCase)) {
                return ['result' => $branch, 'matched' => true, 'case' => (string) $case, 'value' => $value];
            }
        }

        return ['result' => $default, 'matched' => false, 'case' => null, 'value' => $value];
    }

    private function matches(mixed $actual, string $case, string $match, bool $ignoreCase): bool
    {
        // Numbers and numeric text compare by value, so "5" matches 5 and 5.0.
        if ($match === 'equals' && is_numeric($actual) && is_numeric($case)) {
            return (float) $actual === (float) $case;
        }

        if ($ignoreCase && is_string($actual)) {
            $actual = mb_strtolower($actual);
            $case = mb_strtolower($case);
        }

        return ConditionEvaluator::matches($actual, $match, $case);
    }
}
