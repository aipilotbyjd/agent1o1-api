<?php

namespace App\Engine\Trigger;

use App\Models\Trigger;

class TriggerFilterEvaluator
{
    /**
     * Evaluate the trigger's configured filters against event data.
     * Filters live in trigger settings as: [{field, operator, value}, ...]
     */
    public function passes(Trigger $trigger, array $eventData): bool
    {
        $filters = $trigger->settings['filters'] ?? [];

        if (empty($filters)) {
            return true;
        }

        $combinator = $trigger->settings['filter_combinator'] ?? 'and';
        $results = array_map(
            fn (array $filter) => $this->evaluateFilter($filter, $eventData),
            $filters,
        );

        return $combinator === 'or'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    private function evaluateFilter(array $filter, array $eventData): bool
    {
        $actual = data_get($eventData, $filter['field'] ?? '');
        $expected = $filter['value'] ?? null;

        return match ($filter['operator'] ?? '==') {
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'in' => is_array($expected) && in_array($actual, $expected),
            'exists' => $actual !== null,
            'not_exists' => $actual === null,
            default => true,
        };
    }
}
