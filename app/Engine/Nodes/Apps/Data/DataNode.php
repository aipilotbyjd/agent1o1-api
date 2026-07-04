<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class DataNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $data = $input->config['data'] ?? $input->inputData;

        return match ($operation) {
            'get' => $this->success(['result' => data_get($data, $input->config['path'] ?? '')]),
            'set' => $this->set($data, $input),
            'pick' => $this->pick($data, $input),
            'omit' => $this->omit($data, $input),
            'rename_keys' => $this->renameKeys($data, $input),
            'default' => $this->success(['result' => $data]),
            default => $this->fail("Data: unknown operation '{$operation}'"),
        };
    }

    private function set(mixed $data, NodeInput $input): NodeResult
    {
        $result = (array) $data;
        data_set($result, $input->config['path'] ?? '', $input->config['value'] ?? null);

        return $this->success(['result' => $result]);
    }

    private function pick(mixed $data, NodeInput $input): NodeResult
    {
        $keys = (array) ($input->config['keys'] ?? []);

        return $this->success(['result' => array_intersect_key((array) $data, array_flip($keys))]);
    }

    private function omit(mixed $data, NodeInput $input): NodeResult
    {
        $keys = (array) ($input->config['keys'] ?? []);

        return $this->success(['result' => array_diff_key((array) $data, array_flip($keys))]);
    }

    private function renameKeys(mixed $data, NodeInput $input): NodeResult
    {
        $mapping = (array) ($input->config['mapping'] ?? []);
        $result = [];

        foreach ((array) $data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return $this->success(['result' => $result]);
    }
}
