<?php

namespace App\Agents\Tools;

use App\Engine\NodeCatalog;
use App\Engine\NodeInput;
use App\Enums\ExecutionNodeStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WorkflowNodeTool implements Tool
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        private string $nodeType,
        private string $toolName,
        private string $toolDescription,
        private array $inputSchema = [],
        private array $credentials = [],
    ) {}

    public function description(): Stringable|string
    {
        return $this->toolDescription;
    }

    public function handle(Request $request): Stringable|string
    {
        $handlerClass = NodeCatalog::resolve($this->nodeType);

        if ($handlerClass === null) {
            return json_encode(['error' => "Unknown node type: {$this->nodeType}"], JSON_THROW_ON_ERROR);
        }

        $handler = new $handlerClass;

        $payload = new NodeInput(
            nodeId: 'ai-agent-tool-'.uniqid(),
            nodeType: $this->nodeType,
            nodeName: $this->toolName,
            config: array_merge(
                ['operation' => $this->resolveOperation()],
                $request->all(),
            ),
            inputData: $request->all(),
            credentials: $this->credentials,
            variables: [],
            executionMeta: [],
        );

        $result = $handler->handle($payload);

        if ($result->status === ExecutionNodeStatus::Failed) {
            return json_encode([
                'error' => $result->error['message'] ?? 'Tool execution failed',
                'code' => $result->error['code'] ?? 'TOOL_ERROR',
            ], JSON_THROW_ON_ERROR);
        }

        return json_encode($result->output, JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->convertToSdkSchema($schema, $this->inputSchema);
    }

    private function resolveOperation(): string
    {
        $parts = explode('.', $this->nodeType, 2);

        return $parts[1] ?? $parts[0];
    }

    /**
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    private function convertToSdkSchema(JsonSchema $schema, array $jsonSchema): array
    {
        $properties = $jsonSchema['properties'] ?? [];
        $required = $jsonSchema['required'] ?? [];
        $result = [];

        foreach ($properties as $name => $property) {
            $type = $property['type'] ?? 'string';
            $isRequired = in_array($name, $required, true);

            $field = match ($type) {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                'array' => $schema->array()->items($schema->string()),
                'object' => $schema->object(),
                default => $schema->string(),
            };

            if ($isRequired) {
                $field = $field->required();
            }

            $result[$name] = $field;
        }

        return $result;
    }
}
