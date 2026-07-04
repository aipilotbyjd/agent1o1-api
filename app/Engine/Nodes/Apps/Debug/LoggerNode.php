<?php

namespace App\Engine\Nodes\Apps\Debug;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Log;

class LoggerNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $level = $input->config['level'] ?? 'info';
        $message = $input->config['message'] ?? '';
        $context = [
            'execution_id' => $input->executionMeta['execution_id'] ?? null,
            'node_id' => $input->nodeId,
            'data' => $input->config['data'] ?? $input->inputData,
        ];

        match ($level) {
            'debug' => Log::debug($message, $context),
            'warning' => Log::warning($message, $context),
            'error' => Log::error($message, $context),
            default => Log::info($message, $context),
        };

        return $this->success([
            'logged' => true,
            'level' => $level,
            'message' => $message,
        ]);
    }
}
