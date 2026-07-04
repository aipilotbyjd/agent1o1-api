<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class MathNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $a = (float) ($input->config['a'] ?? 0);
        $b = (float) ($input->config['b'] ?? 0);
        $values = array_map(floatval(...), (array) ($input->config['values'] ?? []));

        return match ($operation) {
            'add' => $this->success(['result' => $a + $b]),
            'subtract' => $this->success(['result' => $a - $b]),
            'multiply' => $this->success(['result' => $a * $b]),
            'divide' => $b == 0.0
                ? $this->fail('Math: division by zero')
                : $this->success(['result' => $a / $b]),
            'modulo' => $this->success(['result' => fmod($a, $b)]),
            'power' => $this->success(['result' => $a ** $b]),
            'sqrt' => $this->success(['result' => sqrt($a)]),
            'abs' => $this->success(['result' => abs($a)]),
            'round' => $this->success(['result' => round($a, (int) ($input->config['precision'] ?? 0))]),
            'floor' => $this->success(['result' => floor($a)]),
            'ceil' => $this->success(['result' => ceil($a)]),
            'sum' => $this->success(['result' => array_sum($values)]),
            'avg' => $this->success(['result' => count($values) > 0 ? array_sum($values) / count($values) : 0]),
            'min' => $this->success(['result' => count($values) > 0 ? min($values) : null]),
            'max' => $this->success(['result' => count($values) > 0 ? max($values) : null]),
            'random' => $this->success(['result' => random_int((int) $a, (int) max($a, $b))]),
            default => $this->fail("Math: unknown operation '{$operation}'"),
        };
    }
}
