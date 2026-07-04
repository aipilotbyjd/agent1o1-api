<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Str;

class StringNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $text = (string) ($input->config['text'] ?? '');

        return match ($operation) {
            'uppercase' => $this->success(['result' => mb_strtoupper($text)]),
            'lowercase' => $this->success(['result' => mb_strtolower($text)]),
            'title_case' => $this->success(['result' => Str::title($text)]),
            'trim' => $this->success(['result' => trim($text)]),
            'length' => $this->success(['result' => mb_strlen($text)]),
            'split' => $this->success(['result' => explode($input->config['separator'] ?? ',', $text)]),
            'replace' => $this->success(['result' => str_replace($input->config['search'] ?? '', $input->config['replace'] ?? '', $text)]),
            'substring' => $this->success(['result' => mb_substr($text, (int) ($input->config['start'] ?? 0), isset($input->config['length']) ? (int) $input->config['length'] : null)]),
            'contains' => $this->success(['result' => str_contains($text, (string) ($input->config['search'] ?? ''))]),
            'slug' => $this->success(['result' => Str::slug($text)]),
            'camel' => $this->success(['result' => Str::camel($text)]),
            'snake' => $this->success(['result' => Str::snake($text)]),
            'concat' => $this->success(['result' => $text.($input->config['with'] ?? '')]),
            'pad' => $this->success(['result' => str_pad($text, (int) ($input->config['length'] ?? 0), $input->config['pad'] ?? ' ')]),
            'regex_match' => $this->regexMatch($text, $input),
            'regex_replace' => $this->success(['result' => preg_replace($input->config['pattern'] ?? '//', $input->config['replace'] ?? '', $text)]),
            default => $this->fail("String: unknown operation '{$operation}'"),
        };
    }

    private function regexMatch(string $text, NodeInput $input): NodeResult
    {
        preg_match_all($input->config['pattern'] ?? '//', $text, $matches);

        return $this->success(['result' => $matches[0] ?? [], 'groups' => $matches]);
    }
}
