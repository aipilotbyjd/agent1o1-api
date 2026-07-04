<?php

namespace App\Agents\Tools;

use App\Models\Node;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class InspectNodeSchemaTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get the full schema for a specific node type: required config fields, optional config fields, credential requirements, input/output schema, and a sample config. Always call this before setting a node\'s config.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodeType = $request['node_type'] ?? null;

        $data = Cache::remember("node_schema:{$nodeType}", 300, function () use ($nodeType) {
            $node = Node::query()->where('type', $nodeType)->first();

            if ($node === null) {
                return null;
            }

            $configSchema = $node->config_schema ?? [];
            $required = [];
            $optional = [];

            foreach ($configSchema['properties'] ?? [] as $field => $def) {
                $isRequired = in_array($field, $configSchema['required'] ?? [], true);
                $entry = [
                    'field' => $field,
                    'type' => $def['type'] ?? 'string',
                    'description' => $def['description'] ?? null,
                ];

                if ($isRequired) {
                    $required[] = $entry;
                } else {
                    $optional[] = $entry;
                }
            }

            $sampleConfig = [];
            foreach ($required as $f) {
                $sampleConfig[$f['field']] = match ($f['type']) {
                    'integer', 'number' => 0,
                    'boolean' => false,
                    'array' => [],
                    default => "your_{$f['field']}_here",
                };
            }

            return [
                'type' => $node->type,
                'name' => $node->name,
                'credential_type' => $node->credential_type,
                'required_config_fields' => $required,
                'optional_config_fields' => $optional,
                'sample_config' => $sampleConfig,
                'input_schema' => $node->input_schema,
                'output_schema' => $node->output_schema,
            ];
        });

        if ($data === null) {
            return json_encode(['error' => "Node type '{$nodeType}' not found"], JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'node_type' => $schema->string()->required()->description('The node type key to inspect (e.g. "slack_message", "webhook_trigger")'),
        ];
    }
}
