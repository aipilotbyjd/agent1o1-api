<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;

class TransformNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        // Config already has expressions resolved — output is the resolved config
        $output = $input->config['output'] ?? $input->config;

        // If a specific mapping is defined, apply it
        if (isset($input->config['mappings']) && is_array($input->config['mappings'])) {
            $output = [];
            foreach ($input->config['mappings'] as $key => $value) {
                $output[$key] = $value;
            }
        }

        return NodeResult::completed(is_array($output) ? $output : ['value' => $output]);
    }
}
