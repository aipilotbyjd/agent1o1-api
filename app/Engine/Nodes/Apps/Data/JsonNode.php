<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class JsonNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'parse' => $this->parse($input),
            'stringify' => $this->success(['result' => json_encode($input->config['data'] ?? null)]),
            'extract' => $this->success(['result' => data_get($input->config['data'] ?? [], $input->config['path'] ?? '')]),
            'merge' => $this->success(['result' => array_merge((array) ($input->config['data'] ?? []), (array) ($input->config['with'] ?? []))]),
            'keys' => $this->success(['result' => array_keys((array) ($input->config['data'] ?? []))]),
            'values' => $this->success(['result' => array_values((array) ($input->config['data'] ?? []))]),
            default => $this->fail("Json: unknown operation '{$operation}'"),
        };
    }

    private function parse(NodeInput $input): NodeResult
    {
        $decoded = json_decode($input->config['json'] ?? '', true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->fail('JSON parse error: '.json_last_error_msg());
        }

        return $this->success(['result' => $decoded]);
    }
}
