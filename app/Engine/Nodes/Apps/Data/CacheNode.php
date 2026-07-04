<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Cache;

class CacheNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $workspaceId = $input->executionMeta['workspace_id'] ?? 'global';
        $key = "engine:{$workspaceId}:".($input->config['key'] ?? '');

        return match ($operation) {
            'get' => $this->success(['value' => Cache::get($key)]),
            'put' => $this->put($key, $input),
            'forget' => $this->success(['forgotten' => Cache::forget($key)]),
            'has' => $this->success(['exists' => Cache::has($key)]),
            'increment' => $this->success(['value' => Cache::increment($key, (int) ($input->config['by'] ?? 1))]),
            default => $this->fail("Cache: unknown operation '{$operation}'"),
        };
    }

    private function put(string $key, NodeInput $input): NodeResult
    {
        $ttl = (int) ($input->config['ttl_seconds'] ?? 3600);
        Cache::put($key, $input->config['value'] ?? null, $ttl);

        return $this->success(['stored' => true, 'ttl_seconds' => $ttl]);
    }
}
