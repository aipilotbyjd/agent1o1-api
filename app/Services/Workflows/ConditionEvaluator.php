<?php

namespace App\Services\Workflows;

use InvalidArgumentException;

/**
 * The one place a "value, operator, compare-to" condition is decided.
 *
 * The Filter and Router nodes and a trigger's "Only run when" conditions all
 * ask here, so an operator means the same thing wherever it is used. An
 * operator that isn't known is an error, never a quiet pass: a typo must not
 * turn a guard into "always run".
 */
final class ConditionEvaluator
{
    /**
     * Operator => label, in the order the editor lists them.
     *
     * @var array<string, string>
     */
    public const array OPERATORS = [
        'equals' => 'Equals',
        'not_equals' => 'Does not equal',
        'contains' => 'Contains',
        'not_contains' => 'Does not contain',
        'starts_with' => 'Starts with',
        'ends_with' => 'Ends with',
        'greater_than' => 'Greater than',
        'greater_or_equal' => 'Greater than or equal to',
        'less_than' => 'Less than',
        'less_or_equal' => 'Less than or equal to',
        'in_list' => 'Is one of (comma-separated)',
        'matches_regex' => 'Matches pattern (regex)',
        'is_empty' => 'Is empty',
        'is_not_empty' => 'Is not empty',
        'is_true' => 'Is true',
        'is_false' => 'Is false',
    ];

    /**
     * Operators that compare to nothing, so "Compare to" isn't needed.
     *
     * @var list<string>
     */
    public const array UNARY = ['is_empty', 'is_not_empty', 'is_true', 'is_false'];

    /**
     * A longer pattern is refused: it is typed by a user and run on every event.
     */
    private const int MAX_PATTERN_LENGTH = 200;

    /**
     * Whether `$actual` satisfies `$operator` against `$expected`.
     *
     * @throws InvalidArgumentException when the operator isn't one of OPERATORS.
     */
    public static function matches(mixed $actual, string $operator, mixed $expected = null): bool
    {
        return match ($operator) {
            'equals' => self::equals($actual, $expected),
            'not_equals' => ! self::equals($actual, $expected),
            'contains' => self::contains($actual, $expected),
            'not_contains' => ! self::contains($actual, $expected),
            'starts_with' => self::isText($actual) && str_starts_with((string) $actual, (string) $expected),
            'ends_with' => self::isText($actual) && str_ends_with((string) $actual, (string) $expected),
            'greater_than' => self::bothNumeric($actual, $expected) && $actual + 0 > $expected + 0,
            'greater_or_equal' => self::bothNumeric($actual, $expected) && $actual + 0 >= $expected + 0,
            'less_than' => self::bothNumeric($actual, $expected) && $actual + 0 < $expected + 0,
            'less_or_equal' => self::bothNumeric($actual, $expected) && $actual + 0 <= $expected + 0,
            'in_list' => self::inList($actual, $expected),
            'matches_regex' => self::matchesRegex($actual, $expected),
            'is_empty' => self::isBlank($actual),
            'is_not_empty' => ! self::isBlank($actual),
            'is_true' => self::asBoolean($actual) === true,
            'is_false' => self::asBoolean($actual) === false,
            default => throw new InvalidArgumentException("Unknown condition operator [{$operator}]."),
        };
    }

    /**
     * Whether the operator is one `matches()` understands.
     */
    public static function isKnown(string $operator): bool
    {
        return array_key_exists($operator, self::OPERATORS);
    }

    /**
     * Whether a pattern is a usable regular expression.
     */
    public static function isValidPattern(mixed $pattern): bool
    {
        if (! is_string($pattern) || $pattern === '' || strlen($pattern) > self::MAX_PATTERN_LENGTH) {
            return false;
        }

        return @preg_match(self::regex($pattern), '') !== false;
    }

    /**
     * Numbers and numeric strings compare by value ("5" equals 5), anything
     * else must match exactly: so "abc" never equals 0, and null never
     * equals "".
     */
    private static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            return self::asBoolean($actual) === self::asBoolean($expected) && self::asBoolean($actual) !== null;
        }

        if (self::bothNumeric($actual, $expected)) {
            return $actual + 0 == $expected + 0;
        }

        if (is_array($actual) || is_array($expected)) {
            return $actual === $expected;
        }

        return $actual === null || $expected === null
            ? $actual === $expected
            : (string) $actual === (string) $expected;
    }

    /**
     * Text contains text, or a list holds the value.
     */
    private static function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (self::equals($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return self::isText($actual) && $expected !== null && str_contains((string) $actual, (string) $expected);
    }

    /**
     * Whether the value is one of a comma-separated list (or a real list).
     */
    private static function inList(mixed $actual, mixed $expected): bool
    {
        $choices = is_array($expected)
            ? $expected
            : array_map('trim', explode(',', (string) $expected));

        foreach ($choices as $choice) {
            if (self::equals($actual, $choice)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesRegex(mixed $actual, mixed $pattern): bool
    {
        if (! self::isText($actual) || ! self::isValidPattern($pattern)) {
            return false;
        }

        return @preg_match(self::regex($pattern), (string) $actual) === 1;
    }

    /**
     * The user types the pattern body; the delimiter is ours so it can't be
     * used to smuggle in modifiers. Also how the String node reads patterns.
     */
    public static function regex(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u';
    }

    /**
     * Nothing there: null, an empty string or an empty list. 0, "0" and
     * false are values, not blanks.
     */
    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }

    private static function asBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && in_array($value, [0, 1], true)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
    }

    private static function isText(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value);
    }

    private static function bothNumeric(mixed $actual, mixed $expected): bool
    {
        return is_numeric($actual) && is_numeric($expected) && ! is_bool($actual) && ! is_bool($expected);
    }
}
