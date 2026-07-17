<?php

namespace App\Engine;

readonly class NodeInput
{
    public function __construct(
        public string $nodeId,
        public string $nodeType,
        public string $nodeName,
        public array $config,
        public array $inputData,
        public ?array $credentials,
        public array $variables,
        public array $executionMeta,
        public ?string $nodeRunKey = null,
    ) {}

    /**
     * @param  array<string,mixed>  $extraContext  Extra roots merged into the
     *   expression context — used by per-node Loop Mode to expose the current
     *   `item`/`index` so config tokens like `{{ item.title }}` resolve per iteration.
     */
    public static function build(
        string $nodeId,
        WorkflowGraph $graph,
        WorkflowContext $context,
        ?string $nodeRunKey = null,
        array $extraContext = [],
    ): self {
        $node = $graph->getNode($nodeId);
        $compiledConfig = $graph->getCompiledConfig($nodeId);
        $expressionContext = array_merge($context->buildExpressionContext(), $extraContext);
        // Missing tokens render as '' at run time so a typo or absent upstream
        // field never crashes a node mid-execution.
        $resolver = app(Graph\ExpressionResolver::class)->withMissingValue('');

        $config = $resolver->resolveConfig($compiledConfig, $expressionContext);
        $inputData = $context->gatherInputData($nodeId);
        $credentials = $context->getCredential($nodeId);

        return new self(
            nodeId: $nodeId,
            nodeType: $node['type'] ?? '',
            nodeName: $node['name'] ?? $nodeId,
            config: $config,
            inputData: $inputData,
            credentials: $credentials,
            variables: $context->getVariables(),
            executionMeta: [
                'execution_id' => $context->executionId,
                'workspace_id' => $context->workspaceId,
            ],
            nodeRunKey: $nodeRunKey ?? $nodeId,
        );
    }
}
