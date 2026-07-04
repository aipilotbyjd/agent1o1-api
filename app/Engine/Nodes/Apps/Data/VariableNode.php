<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use App\Models\Variable;

class VariableNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $workspaceId = $input->executionMeta['workspace_id'];
        $key = $input->config['key'] ?? '';

        return match ($operation) {
            'get' => $this->get($workspaceId, $key, $input),
            'list' => $this->list($workspaceId),
            default => $this->fail("Variable: unknown operation '{$operation}'"),
        };
    }

    private function get(string $workspaceId, string $key, NodeInput $input): NodeResult
    {
        // Prefer in-context variables (already loaded), fall back to DB
        if (array_key_exists($key, $input->variables)) {
            return $this->success(['key' => $key, 'value' => $input->variables[$key]]);
        }

        $variable = Variable::where('workspace_id', $workspaceId)->where('key', $key)->first();

        if (! $variable) {
            return $this->success(['key' => $key, 'value' => $input->config['default'] ?? null]);
        }

        return $this->success(['key' => $key, 'value' => $variable->resolvedValue()]);
    }

    private function list(string $workspaceId): NodeResult
    {
        $variables = Variable::where('workspace_id', $workspaceId)
            ->where('is_secret', false)
            ->pluck('value', 'key');

        return $this->success(['variables' => $variables->all()]);
    }
}
