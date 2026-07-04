<?php

use App\Engine\Graph\ExpressionResolver;

beforeEach(function () {
    $this->resolver = new ExpressionResolver;
});

test('plain strings pass through untouched', function () {
    expect($this->resolver->evaluate('hello world', []))->toBe('hello world');
});

test('resolves node output paths', function () {
    $context = ['nodes' => ['fetch' => ['output' => ['name' => 'Jay']]]];

    expect($this->resolver->evaluate('{{ nodes.fetch.output.name }}', $context))->toBe('Jay');
});

test('resolves variables', function () {
    $context = ['variables' => ['api_url' => 'https://example.com']];

    expect($this->resolver->evaluate('{{ variables.api_url }}', $context))->toBe('https://example.com');
});

test('interpolates expressions inside strings', function () {
    $context = ['nodes' => ['user' => ['output' => ['name' => 'Jay']]]];

    expect($this->resolver->evaluate('Hello {{ nodes.user.output.name }}!', $context))
        ->toBe('Hello Jay!');
});

test('unknown paths resolve to null', function () {
    expect($this->resolver->evaluate('{{ nodes.missing.output.x }}', []))->toBeNull();
});

test('string functions work', function () {
    expect($this->resolver->evaluate('{{ uppercase(hello) }}', ['hello' => 'hi']))->toBe('HI');
});

test('math sum over arrays', function () {
    $context = ['nodes' => ['calc' => ['output' => ['values' => [1, 2, 3]]]]];

    expect($this->resolver->evaluate('{{ sum(nodes.calc.output.values) }}', $context))->toBe(6);
});

test('if() picks branches', function () {
    $context = ['variables' => ['flag' => true]];

    expect($this->resolver->evaluate('{{ if(variables.flag, yes, no) }}', [
        'variables' => ['flag' => true],
        'yes' => 'YES',
        'no' => 'NO',
    ]))->toBe('YES');
});

test('compileConfig and resolveConfig round-trip nested configs', function () {
    $config = [
        'url' => '{{ variables.base }}/users',
        'nested' => ['key' => '{{ variables.token }}'],
        'static' => 42,
    ];

    $compiled = $this->resolver->compileConfig($config);
    $resolved = $this->resolver->resolveConfig($compiled, [
        'variables' => ['base' => 'https://api.test', 'token' => 'abc'],
    ]);

    expect($resolved['url'])->toBe('https://api.test/users')
        ->and($resolved['nested']['key'])->toBe('abc')
        ->and($resolved['static'])->toBe(42);
});

test('extractNodeDependencies finds upstream node references', function () {
    $deps = $this->resolver->extractNodeDependencies('{{ nodes.alpha.output.x }} and {{ nodes.beta.output.y }}');

    expect($deps)->toContain('alpha')->toContain('beta');
});

// ── Literal value support in evaluateExpression ───────────────────────────────

test('single-quoted string literal resolves to its content', function () {
    expect($this->resolver->evaluate("{{ 'hello world' }}", []))->toBe('hello world');
});

test('double-quoted string literal resolves to its content', function () {
    expect($this->resolver->evaluate('{{ "hello world" }}', []))->toBe('hello world');
});

test('integer literal resolves to int', function () {
    expect($this->resolver->evaluate('{{ 42 }}', []))->toBe(42);
});

test('float literal resolves to float', function () {
    expect($this->resolver->evaluate('{{ 3.14 }}', []))->toBe(3.14);
});

test('true literal resolves to boolean true', function () {
    expect($this->resolver->evaluate('{{ true }}', []))->toBeTrue();
});

test('false literal resolves to boolean false', function () {
    expect($this->resolver->evaluate('{{ false }}', []))->toBeFalse();
});

test('null literal resolves to null', function () {
    expect($this->resolver->evaluate('{{ null }}', []))->toBeNull();
});

// ── parseArgs handles quoted strings with commas ──────────────────────────────

test('strings.replace with comma inside quoted arg splits correctly', function () {
    $context = ['nodes' => ['A' => ['output' => ['text' => 'hello,world']]]];

    $result = $this->resolver->evaluate(
        "{{ strings.replace(nodes.A.output.text, ',', '') }}",
        $context,
    );

    expect($result)->toBe('helloworld');
});

test('function args with quoted strings containing parentheses parse correctly', function () {
    $context = ['nodes' => ['A' => ['output' => ['text' => '(wrapped)']]]];

    $result = $this->resolver->evaluate(
        "{{ strings.replace(nodes.A.output.text, '(', '[') }}",
        $context,
    );

    expect($result)->toBe('[wrapped)');
});
