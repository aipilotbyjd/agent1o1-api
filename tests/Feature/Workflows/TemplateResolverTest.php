<?php

use App\Services\Workflows\ExpressionEvaluator;
use App\Services\Workflows\TemplateResolver;

function resolver(): TemplateResolver
{
    return new TemplateResolver(new ExpressionEvaluator);
}

it('returns non-string, non-array values unchanged', function () {
    expect(resolver()->resolve(42, []))->toBe(42);
    expect(resolver()->resolve(true, []))->toBeTrue();
    expect(resolver()->resolve(null, []))->toBeNull();
});

it('leaves a plain string with no {{ }} untouched', function () {
    expect(resolver()->resolve('input.name', ['input' => ['name' => 'Ada']]))->toBe('input.name');
});

it('resolves a whole-string expression to its raw typed value', function () {
    $context = ['input' => ['count' => 5, 'items' => [1, 2, 3], 'active' => true]];

    expect(resolver()->resolve('{{ input.count }}', $context))->toBe(5);
    expect(resolver()->resolve('{{ input.items }}', $context))->toBe([1, 2, 3]);
    expect(resolver()->resolve('{{ input.active }}', $context))->toBeTrue();
});

it('interpolates an embedded expression as a string', function () {
    $context = ['input' => ['name' => 'Ada']];

    expect(resolver()->resolve('Hello, {{ input.name }}!', $context))->toBe('Hello, Ada!');
});

it('interpolates multiple expressions in one string', function () {
    $context = ['input' => ['first' => 'Ada'], 'nodes' => ['a' => ['last' => 'Lovelace']]];

    expect(resolver()->resolve('{{ input.first }} {{ nodes.a.last }}', $context))->toBe('Ada Lovelace');
});

it('resolves nested array-index syntax', function () {
    $context = ['nodes' => ['a' => ['results' => ['x', 'y', 'z']]]];

    expect(resolver()->resolve('{{ nodes.a.results[1] }}', $context))->toBe('y');
});

it('resolves an unknown path to null (whole) or empty string (embedded)', function () {
    expect(resolver()->resolve('{{ input.missing }}', []))->toBeNull();
    expect(resolver()->resolve('value: {{ input.missing }}', []))->toBe('value: ');
});

it('leaves a syntactically unsafe expression as literal text', function () {
    $value = 'total: {{ 1 + 1 }}';

    expect(resolver()->resolve($value, []))->toBe($value);
});

it('recursively resolves nested arrays, preserving keys', function () {
    $context = ['input' => ['name' => 'Ada']];

    $config = [
        'greeting' => 'Hi {{ input.name }}',
        'nested' => ['value' => '{{ input.name }}', 'literal' => 'unchanged'],
    ];

    expect(resolver()->resolve($config, $context))->toBe([
        'greeting' => 'Hi Ada',
        'nested' => ['value' => 'Ada', 'literal' => 'unchanged'],
    ]);
});

it('stringifies an array value substituted into an embedded expression as json', function () {
    $context = ['input' => ['tags' => ['a', 'b']]];

    expect(resolver()->resolve('tags: {{ input.tags }}', $context))->toBe('tags: ["a","b"]');
});
