<?php

namespace App\Services\Workflows;

use Illuminate\Support\Arr;

/**
 * Resolves one already-validated (by `SafePattern`) `{{ }}` expression
 * against the run's templating context. A path that doesn't resolve to
 * anything returns `null`, exactly like `Arr::get()`: there's no separate
 * "undefined" state.
 */
final class ExpressionEvaluator
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(string $expression, array $context): mixed
    {
        return Arr::get($context, TemplatePaths::normalize($expression));
    }
}
