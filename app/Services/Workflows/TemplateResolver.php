<?php

namespace App\Services\Workflows;

/**
 * The `{{ node.output }}` templating engine a node's `config` is resolved
 * through before `execute()` (or a pause/coordinator path) ever sees it:
 * wired in by `WorkflowRunner::executeStep()`. See docs/WORKFLOWS_PLAN.md's
 * `TemplateResolver`/`ExpressionEvaluator`/`SafePattern`/`TemplatePaths`
 * entry.
 *
 * Existing dot-path config fields (`TransformNode`'s `mapping` values,
 * `RouterNode`/`FilterNode`'s `path`, `LoopNode`'s `items_path`, ...)
 * predate this stage and were never `{{ }}`-wrapped: they pass through
 * untouched here, since resolution only ever touches substrings that
 * actually contain `{{`.
 */
final class TemplateResolver
{
    public function __construct(private readonly ExpressionEvaluator $evaluator) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->resolve($item, $context), $value);
        }

        if (! is_string($value) || ! str_contains($value, '{{')) {
            return $value;
        }

        if (preg_match(SafePattern::WHOLE, $value, $matches) === 1) {
            return $this->evaluator->evaluate($matches[1], $context);
        }

        return preg_replace_callback(
            SafePattern::EMBEDDED,
            fn (array $matches): string => $this->stringify($this->evaluator->evaluate($matches[1], $context)),
            $value,
        );
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_array($value) => json_encode($value),
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
