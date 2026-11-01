<?php

namespace App\Services\Agents\Approvals;

use Illuminate\Support\Str;

/**
 * Checks one rule condition: `{field, op, value}`; against a tool call's
 * effective arguments (what the model sent merged with the values bound at
 * attach time, so a rule can see a fixed channel too).
 *
 * `field` is a dot path into the arguments. A field that isn't there only
 * matches `missing`, so "ask when `amount` > 500" never fires on a call with
 * no amount at all.
 *
 * The `domain_*` operators read every address in the field (a comma or
 * semicolon separated list, or an array) so one outside recipient hidden
 * among internal ones is still caught.
 */
class ConditionEvaluator
{
    /**
     * @var list<string>
     */
    public const array OPERATORS = [
        'eq', 'neq', 'in', 'not_in', 'contains', 'not_contains', 'gt', 'gte', 'lt', 'lte',
        'domain_in', 'domain_not_in', 'matches', 'count_gt', 'exists', 'missing',
    ];

    /**
     * @param  array{field?: string, op?: string, value?: mixed}  $condition
     * @param  array<string, mixed>  $arguments
     */
    public function matches(array $condition, array $arguments): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $operator = (string) ($condition['op'] ?? 'eq');
        $expected = $condition['value'] ?? null;
        $present = $field !== '' && data_get($arguments, $field, $this) !== $this;
        $actual = $present ? data_get($arguments, $field) : null;

        if ($operator === 'exists') {
            return $present;
        }

        if ($operator === 'missing') {
            return ! $present;
        }

        if (! $present) {
            return false;
        }

        return match ($operator) {
            'eq' => $this->scalar($actual) === $this->scalar($expected),
            'neq' => $this->scalar($actual) !== $this->scalar($expected),
            'in' => in_array($this->scalar($actual), $this->list($expected), true),
            'not_in' => ! in_array($this->scalar($actual), $this->list($expected), true),
            'contains' => Str::contains($this->text($actual), (string) $expected, ignoreCase: true),
            'not_contains' => ! Str::contains($this->text($actual), (string) $expected, ignoreCase: true),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'domain_in' => $this->domains($actual) !== [] && array_diff($this->domains($actual), $this->list($expected)) === [],
            'domain_not_in' => array_diff($this->domains($actual), $this->list($expected)) !== [],
            'matches' => $this->matchesPattern((string) $expected, $this->text($actual)),
            'count_gt' => is_numeric($expected) && count($this->items($actual)) > (int) $expected,
            default => false,
        };
    }

    private function scalar(mixed $value): string
    {
        return mb_strtolower(trim($this->text($value)));
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value);
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[,;]/', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $item): string => $this->scalar($item), $items), fn (string $item): bool => $item !== ''));
    }

    /**
     * @return list<mixed>
     */
    private function items(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value)), fn (string $item): bool => $item !== ''));
    }

    /**
     * @return list<string>
     */
    private function domains(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(function (mixed $address): ?string {
            $address = $this->scalar($address);

            if (preg_match('/<([^>]+)>/', $address, $match) === 1) {
                $address = $match[1];
            }

            return str_contains($address, '@') ? Str::afterLast($address, '@') : null;
        }, $this->items($value)))));
    }

    /**
     * A pattern is matched case-insensitively; one written without
     * delimiters is wrapped in them. An invalid pattern never matches rather
     * than erroring the tool call it guards.
     */
    private function matchesPattern(string $pattern, string $subject): bool
    {
        if ($pattern === '') {
            return false;
        }

        $delimited = preg_match('/^([^a-zA-Z0-9\\\\\s]).*\1[a-zA-Z]*$/s', $pattern) === 1 ? $pattern : '~'.str_replace('~', '\~', $pattern).'~i';

        return @preg_match($delimited, $subject) === 1;
    }
}
