<?php

use App\Services\Workflows\ConditionEvaluator;

it('decides each operator', function (mixed $actual, string $operator, mixed $expected, bool $result) {
    expect(ConditionEvaluator::matches($actual, $operator, $expected))->toBe($result);
})->with([
    'equals text' => ['open', 'equals', 'open', true],
    'equals number as text' => [5, 'equals', '5', true],
    'text is not zero' => ['abc', 'equals', 0, false],
    'null is not empty text' => [null, 'equals', '', false],
    'boolean as text' => [true, 'equals', 'true', true],
    'not equals' => ['a', 'not_equals', 'b', true],
    'contains text' => ['hello world', 'contains', 'lo wo', true],
    'contains in a list' => [['a', 'b'], 'contains', 'b', true],
    'list lacks value' => [['a', 'b'], 'contains', 'c', false],
    'not contains' => ['hello', 'not_contains', 'z', true],
    'starts with' => ['invoice-42', 'starts_with', 'invoice', true],
    'ends with' => ['invoice-42', 'ends_with', '-42', true],
    'greater than' => [10, 'greater_than', '5', true],
    'greater or equal' => [5, 'greater_or_equal', 5, true],
    'less than' => [1, 'less_than', 5, true],
    'less or equal' => [6, 'less_or_equal', 5, false],
    'number needed to compare' => ['abc', 'greater_than', 1, false],
    'in a list' => ['b', 'in_list', 'a, b, c', true],
    'not in a list' => ['z', 'in_list', 'a, b, c', false],
    'regex' => ['ORD-1234', 'matches_regex', '^ORD-\d+$', true],
    'regex miss' => ['XYZ', 'matches_regex', '^ORD-\d+$', false],
    'broken regex never matches' => ['x', 'matches_regex', '(', false],
    'null is empty' => [null, 'is_empty', null, true],
    'blank text is empty' => ['  ', 'is_empty', null, true],
    'empty list is empty' => [[], 'is_empty', null, true],
    'zero is a value' => [0, 'is_not_empty', null, true],
    'string zero is a value' => ['0', 'is_empty', null, false],
    'false is a value' => [false, 'is_not_empty', null, true],
    'is true' => ['yes', 'is_true', null, true],
    'is false' => ['0', 'is_false', null, true],
    'unknown text is neither' => ['maybe', 'is_false', null, false],
]);

it('refuses an operator it does not know', function () {
    ConditionEvaluator::matches('a', 'sounds_like', 'b');
})->throws(InvalidArgumentException::class);

it('validates regular expressions', function () {
    expect(ConditionEvaluator::isValidPattern('^a+$'))->toBeTrue()
        ->and(ConditionEvaluator::isValidPattern('('))->toBeFalse()
        ->and(ConditionEvaluator::isValidPattern(''))->toBeFalse()
        ->and(ConditionEvaluator::isValidPattern(str_repeat('a', 201)))->toBeFalse()
        ->and(ConditionEvaluator::isKnown('equals'))->toBeTrue()
        ->and(ConditionEvaluator::isKnown('nope'))->toBeFalse();
});
