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
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Deliberately NOT arbitrary code execution. `docs/NODES_CATALOG.md` flags
 * the old project's `CodeNode` as needing "its own security review at build
 * time" before ever accepting user-submitted PHP/JS to `eval()` or sandbox:
 * that review hasn't happened. Until it does, this node only runs a
 * whitelisted set of pure, side-effect-free operations against `$context`, so
 * the `NodeContract`/engine wiring can be proven end-to-end without opening
 * a code-execution hole.
 */
class RunCodeNode implements DeclaresEffect, HasIcon, NodeContract
{
    private const array ALLOWED_OPERATIONS = ['set', 'copy', 'uppercase', 'lowercase', 'concat'];

    public function type(): string
    {
        return 'run_code';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Run Code';
    }

    public function icon(): string
    {
        return 'source-code';
    }

    public function description(): string
    {
        return 'Runs a whitelisted set of pure operations (set/copy/uppercase/lowercase/concat) against the run context, not arbitrary code execution.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operations'],
            'properties' => [
                'operations' => [
                    'type' => 'array',
                    'title' => 'Steps',
                    'description' => 'Run in order; each step writes one output key.',
                    'x-widget' => 'list',
                    'items' => [
                        'type' => 'object',
                        'required' => ['op', 'output'],
                        'properties' => [
                            'op' => Field::select('Operation', ['set' => 'Set a value', 'copy' => 'Copy a value', 'uppercase' => 'Uppercase', 'lowercase' => 'Lowercase', 'concat' => 'Join values']),
                            'output' => Field::text('Output key', null, 'full_name'),
                            'path' => Field::text('From path', 'Used by Copy, Uppercase and Lowercase.', 'input.name'),
                            'value' => Field::text('Value', 'Used by Set.'),
                            'paths' => Field::list('From paths', 'Used by Join values.', 'input.first_name'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $output = [];

        foreach ($config['operations'] ?? [] as $operation) {
            $op = $operation['op'] ?? null;

            if (! in_array($op, self::ALLOWED_OPERATIONS, true)) {
                throw new InvalidArgumentException("Unsupported RunCodeNode operation [{$op}].");
            }

            $output[$operation['output']] = match ($op) {
                'set' => $operation['value'] ?? null,
                'copy' => Arr::get($context, $operation['path'] ?? ''),
                'uppercase' => Str::upper((string) Arr::get($context, $operation['path'] ?? '')),
                'lowercase' => Str::lower((string) Arr::get($context, $operation['path'] ?? '')),
                'concat' => collect($operation['paths'] ?? [])
                    ->map(fn (string $path) => Arr::get($context, $path))
                    ->implode(''),
            };
        }

        return $output;
    }
}
