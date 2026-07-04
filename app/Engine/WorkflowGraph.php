<?php

namespace App\Engine;

use App\Engine\Graph\ExpressionResolver;
use App\Enums\NodeType;
use RuntimeException;

readonly class WorkflowGraph
{
    public array $nodeMap;

    public array $successors;

    public array $predecessors;

    public array $inDegree;

    public array $startNodes;

    public array $compiledExpressions;

    public array $edgeMap;

    public array $downstreamConsumers;

    private function __construct(
        array $nodeMap,
        array $successors,
        array $predecessors,
        array $inDegree,
        array $startNodes,
        array $compiledExpressions,
        array $edgeMap,
        array $downstreamConsumers,
    ) {
        $this->nodeMap = $nodeMap;
        $this->successors = $successors;
        $this->predecessors = $predecessors;
        $this->inDegree = $inDegree;
        $this->startNodes = $startNodes;
        $this->compiledExpressions = $compiledExpressions;
        $this->edgeMap = $edgeMap;
        $this->downstreamConsumers = $downstreamConsumers;
    }

    public static function compile(array $nodes, array $edges): self
    {
        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[$node['id']] = $node;
        }

        $successors = array_fill_keys(array_keys($nodeMap), []);
        $predecessors = array_fill_keys(array_keys($nodeMap), []);
        $edgeMap = array_fill_keys(array_keys($nodeMap), []);
        $inDegree = array_fill_keys(array_keys($nodeMap), 0);

        foreach ($edges as $edge) {
            $source = $edge['source'];
            $target = $edge['target'];

            if (! isset($nodeMap[$source]) || ! isset($nodeMap[$target])) {
                continue;
            }

            $successors[$source][] = $target;
            $predecessors[$target][] = $source;
            $inDegree[$target]++;
            $edgeMap[$source][] = [
                'target' => $target,
                'sourceHandle' => $edge['sourceHandle'] ?? null,
                'targetHandle' => $edge['targetHandle'] ?? null,
            ];
        }

        self::detectCycles($nodeMap, $successors, $inDegree);

        $startNodes = array_keys(array_filter($inDegree, fn ($d) => $d === 0));

        $resolver = app(ExpressionResolver::class);
        $compiledExpressions = [];
        foreach ($nodeMap as $id => $node) {
            $config = $node['config'] ?? $node['data'] ?? [];
            $compiledExpressions[$id] = $resolver->compileConfig($config);
        }

        $downstreamConsumers = [];
        foreach ($nodeMap as $id => $_) {
            $downstreamConsumers[$id] = count($successors[$id]);
        }

        return new self(
            nodeMap: $nodeMap,
            successors: $successors,
            predecessors: $predecessors,
            inDegree: $inDegree,
            startNodes: $startNodes,
            compiledExpressions: $compiledExpressions,
            edgeMap: $edgeMap,
            downstreamConsumers: $downstreamConsumers,
        );
    }

    public function getNode(string $id): array
    {
        return $this->nodeMap[$id] ?? throw new RuntimeException("Node {$id} not found in graph.");
    }

    public function getNodeType(string $id): NodeType
    {
        $type = $this->nodeMap[$id]['type'] ?? '';

        return NodeType::tryFrom($type) ?? NodeType::Transform;
    }

    public function getSuccessors(string $id): array
    {
        return $this->successors[$id] ?? [];
    }

    public function getPredecessors(string $id): array
    {
        return $this->predecessors[$id] ?? [];
    }

    public function getCompiledConfig(string $id): array
    {
        return $this->compiledExpressions[$id] ?? [];
    }

    public function getEdgesFrom(string $id, ?string $sourceHandle = null): array
    {
        $edges = $this->edgeMap[$id] ?? [];

        if ($sourceHandle === null) {
            return $edges;
        }

        return array_values(array_filter($edges, fn ($e) => $e['sourceHandle'] === $sourceHandle));
    }

    public function nodeCount(): int
    {
        return count($this->nodeMap);
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodeMap[$id]);
    }

    private static function detectCycles(array $nodeMap, array $successors, array $inDegree): void
    {
        $queue = array_keys(array_filter($inDegree, fn ($d) => $d === 0));
        $visited = 0;
        $remaining = $inDegree;

        while ($queue) {
            $node = array_shift($queue);
            $visited++;

            foreach ($successors[$node] ?? [] as $successor) {
                $remaining[$successor]--;
                if ($remaining[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        if ($visited !== count($nodeMap)) {
            throw new RuntimeException('Workflow graph contains a cycle — cannot execute.');
        }
    }
}
