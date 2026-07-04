<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class MergeNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $mode = $input->config['mode'] ?? 'merge'; // merge | append | first
        $inputs = array_values($input->inputData);

        if (empty($inputs)) {
            return NodeResult::completed([]);
        }

        $output = match ($mode) {
            'first' => $inputs[0] ?? [],
            'append' => $this->appendAll($inputs),
            default => $this->mergeAll($inputs),
        };

        return NodeResult::completed($output);
    }

    private function mergeAll(array $inputs): array
    {
        return array_merge(...array_map(fn ($i) => is_array($i) ? $i : [], $inputs));
    }

    private function appendAll(array $inputs): array
    {
        $result = [];
        foreach ($inputs as $input) {
            if (is_array($input)) {
                $result = array_merge($result, array_values($input));
            }
        }

        return $result;
    }
}
