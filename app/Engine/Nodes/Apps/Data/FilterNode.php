<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class FilterNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $items = (array) ($input->config['items'] ?? []);
        $field = $input->config['field'] ?? null;
        $filterOperator = $input->config['filter_operator'] ?? '==';
        $value = $input->config['value'] ?? null;

        $filtered = array_values(array_filter($items, function ($item) use ($field, $filterOperator, $value) {
            $itemValue = $field !== null ? data_get($item, $field) : $item;

            return match ($filterOperator) {
                '==' => $itemValue == $value,
                '!=' => $itemValue != $value,
                '>' => $itemValue > $value,
                '>=' => $itemValue >= $value,
                '<' => $itemValue < $value,
                '<=' => $itemValue <= $value,
                'contains' => is_string($itemValue) && str_contains($itemValue, (string) $value),
                'in' => is_array($value) && in_array($itemValue, $value),
                'not_null' => $itemValue !== null,
                'is_null' => $itemValue === null,
                default => true,
            };
        }));

        return $this->success([
            'result' => $filtered,
            'count' => count($filtered),
            'removed' => count($items) - count($filtered),
        ]);
    }
}
