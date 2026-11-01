<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Arr;

/**
 * Reshapes data: `config.mapping` is `{output_key: "dot.path.into.context"}`.
 * Paths are resolved against `$context` as-is: templating
 * (`{{ node.output }}` expressions) lands in Stage 5, see
 * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md.
 */
class TransformNode implements DeclaresEffect, HasIcon, NodeContract
{
    public function type(): string
    {
        return 'transform';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Transform';
    }

    public function icon(): string
    {
        return 'arrow-data-transfer-horizontal';
    }

    public function description(): string
    {
        return 'Reshapes data by mapping output keys to dot-paths into the run context.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['mapping'],
            'properties' => [
                'mapping' => Field::keyValue('Fields', 'Each output key is set from a path into the run, e.g. nodes.fetch.output.email or input.name.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $mapping = $config['mapping'] ?? [];

        $output = [];

        foreach ($mapping as $outputKey => $path) {
            $output[$outputKey] = Arr::get($context, $path);
        }

        return $output;
    }
}
