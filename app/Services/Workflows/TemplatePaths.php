<?php

namespace App\Services\Workflows;

/**
 * Normalizes a template expression's `[n]` array-index syntax into the plain
 * dot-notation `Arr::get()` understands: `nodes.a[0].field` becomes
 * `nodes.a.0.field`.
 */
final class TemplatePaths
{
    public static function normalize(string $path): string
    {
        $dotted = preg_replace('/\[(\d+)\]/', '.$1', trim($path));

        return trim((string) preg_replace('/\.+/', '.', $dotted), '.');
    }

    /**
     * Every `{{ }}` expression referenced anywhere in a config tree,
     * normalized: used by `DryRunner` to flag a path nothing in the
     * simulated context provides.
     *
     * @param  mixed  $value
     * @return array<int, string>
     */
    public static function referencedIn($value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_merge(
                [],
                ...array_map(fn ($item) => self::referencedIn($item), array_values($value)),
            )));
        }

        if (! is_string($value) || ! str_contains($value, '{{')) {
            return [];
        }

        preg_match_all(SafePattern::EMBEDDED, $value, $matches);

        return array_values(array_unique(array_map(
            fn (string $expression) => self::normalize($expression),
            array_filter($matches[1] ?? []),
        )));
    }
}
