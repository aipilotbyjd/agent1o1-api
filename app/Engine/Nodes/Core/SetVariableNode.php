<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Models\Variable;

class SetVariableNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $key = $input->config['key'] ?? null;
        $value = $input->config['value'] ?? null;
        $scope = $input->config['scope'] ?? 'workspace'; // workspace | execution

        if (empty($key)) {
            return NodeResult::failed('Variable key is required');
        }

        if ($scope === 'workspace') {
            Variable::updateOrCreate(
                [
                    'workspace_id' => $input->executionMeta['workspace_id'],
                    'key' => $key,
                ],
                [
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                    'created_by' => 1, // system
                ],
            );
        }

        return NodeResult::completed([
            'key' => $key,
            'value' => $value,
            'scope' => $scope,
        ]);
    }
}
