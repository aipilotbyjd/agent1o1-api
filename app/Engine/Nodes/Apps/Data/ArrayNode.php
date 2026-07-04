<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class ArrayNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $items = (array) ($input->config['items'] ?? []);

        return match ($operation) {
            'pluck' => $this->success(['result' => array_column($items, $input->config['key'] ?? null)]),
            'filter' => $this->success(['result' => array_values(array_filter($items))]),
            'unique' => $this->success(['result' => array_values(array_unique($items, SORT_REGULAR))]),
            'sort' => $this->sort($items, $input),
            'reverse' => $this->success(['result' => array_reverse($items)]),
            'slice' => $this->success(['result' => array_slice($items, (int) ($input->config['offset'] ?? 0), isset($input->config['length']) ? (int) $input->config['length'] : null)]),
            'chunk' => $this->success(['result' => array_chunk($items, max(1, (int) ($input->config['size'] ?? 1)))]),
            'flatten' => $this->success(['result' => collect($items)->flatten((int) ($input->config['depth'] ?? 1))->all()]),
            'merge' => $this->success(['result' => array_merge($items, (array) ($input->config['with'] ?? []))]),
            'count' => $this->success(['result' => count($items)]),
            'first' => $this->success(['result' => array_values($items)[0] ?? null]),
            'last' => $this->success(['result' => array_values($items)[count($items) - 1] ?? null]),
            'join' => $this->success(['result' => implode($input->config['separator'] ?? ',', $items)]),
            default => $this->fail("Array: unknown operation '{$operation}'"),
        };
    }

    private function sort(array $items, NodeInput $input): NodeResult
    {
        $key = $input->config['key'] ?? null;
        $descending = (bool) ($input->config['descending'] ?? false);

        $sorted = collect($items)
            ->when($key, fn ($c) => $descending ? $c->sortByDesc($key) : $c->sortBy($key))
            ->when(! $key, fn ($c) => $descending ? $c->sortDesc() : $c->sort())
            ->values()
            ->all();

        return $this->success(['result' => $sorted]);
    }
}
