<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class ConditionNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $conditions = $input->config['conditions'] ?? [];
        $mode = $input->config['mode'] ?? 'if'; // if | switch

        if ($mode === 'switch') {
            return $this->handleSwitch($input, $conditions);
        }

        return $this->handleIf($input, $conditions);
    }

    private function handleIf(NodeInput $input, array $conditions): NodeResult
    {
        $combinator = $input->config['combinator'] ?? 'and'; // and | or
        $result = $this->evaluateConditions($conditions, $combinator);
        $activeBranches = $result ? ['true'] : ['false'];

        return NodeResult::withBranches(
            output: ['result' => $result, 'branch' => $result ? 'true' : 'false'],
            activeBranches: $activeBranches,
        );
    }

    private function handleSwitch(NodeInput $input, array $conditions): NodeResult
    {
        $activeBranches = [];

        foreach ($conditions as $branch) {
            $branchConditions = $branch['conditions'] ?? [];
            $combinator = $branch['combinator'] ?? 'and';
            $branchName = $branch['name'] ?? 'default';

            if ($this->evaluateConditions($branchConditions, $combinator)) {
                $activeBranches[] = $branchName;

                if (! ($input->config['evaluate_all'] ?? false)) {
                    break; // Stop at first match unless evaluate_all is set
                }
            }
        }

        // If no branch matched, use fallback
        if (empty($activeBranches)) {
            $activeBranches[] = $input->config['fallback_branch'] ?? 'default';
        }

        return NodeResult::withBranches(
            output: ['active_branches' => $activeBranches],
            activeBranches: $activeBranches,
        );
    }

    private function evaluateConditions(array $conditions, string $combinator): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $results = array_map(fn ($c) => $this->evaluateCondition($c), $conditions);

        return $combinator === 'or'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    private function evaluateCondition(array $condition): bool
    {
        $left = $condition['left'] ?? null;
        $operator = $condition['operator'] ?? '==';
        $right = $condition['right'] ?? null;

        return match ($operator) {
            '==', 'equals' => $left == $right,
            '===', 'strict_equals' => $left === $right,
            '!=', 'not_equals' => $left != $right,
            '!==', 'strict_not_equals' => $left !== $right,
            '>' => (float) $left > (float) $right,
            '>=' => (float) $left >= (float) $right,
            '<' => (float) $left < (float) $right,
            '<=' => (float) $left <= (float) $right,
            'contains' => is_string($left) && str_contains($left, (string) $right),
            'not_contains' => is_string($left) && ! str_contains($left, (string) $right),
            'starts_with' => is_string($left) && str_starts_with($left, (string) $right),
            'ends_with' => is_string($left) && str_ends_with($left, (string) $right),
            'is_empty' => empty($left),
            'is_not_empty' => ! empty($left),
            'is_null' => $left === null,
            'is_not_null' => $left !== null,
            'in' => is_array($right) && in_array($left, $right),
            'not_in' => is_array($right) && ! in_array($left, $right),
            default => false,
        };
    }
}
