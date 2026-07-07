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

// ── Acceptance criteria: ID-based bare tokens {{node_2.output.city}} ───────────
//
// At run time WorkflowContext::buildExpressionContext() exposes each completed
// node at the top level keyed by its (stable) id, so these bare-id contexts
// mirror exactly what the engine passes the resolver.

test('bare-id token resolves node output field', function () {
    $context = ['node_1' => ['output' => ['email' => 'jay@example.com']]];

    expect($this->resolver->evaluate('{{node_1.output.email}}', $context))
        ->toBe('jay@example.com');
});

test('bare-id nested path resolves via data_get', function () {
    $context = ['node_2' => ['output' => ['data' => ['temp' => 21.5]]]];

    expect($this->resolver->evaluate('{{ node_2.output.data.temp }}', $context))
        ->toBe(21.5);
});

test('bare-id numeric index access resolves via data_get', function () {
    $context = ['node_2' => ['output' => ['data' => [['id' => 7], ['id' => 8]]]]];

    expect($this->resolver->evaluate('{{ node_2.output.data.0.id }}', $context))
        ->toBe(7);
});

// ── Single-token type preservation (whole field is one token) ─────────────────

test('single token preserves array type instead of stringifying', function () {
    $context = ['node_2' => ['output' => ['rows' => [1, 2, 3]]]];

    expect($this->resolver->evaluate('{{ node_2.output.rows }}', $context))
        ->toBe([1, 2, 3]);
});

test('single token preserves object/assoc-array type', function () {
    $payload = ['city' => 'Berlin', 'temp' => 9];
    $context = ['node_2' => ['output' => ['weather' => $payload]]];

    expect($this->resolver->evaluate('{{ node_2.output.weather }}', $context))
        ->toBe($payload);
});

test('single numeric token preserves int type', function () {
    $context = ['node_2' => ['output' => ['count' => 42]]];

    expect($this->resolver->evaluate('{{ node_2.output.count }}', $context))->toBe(42);
});

// ── Missing tokens: configurable, never crash ─────────────────────────────────

test('missing bare-id token defaults to null and does not crash', function () {
    expect($this->resolver->evaluate('{{ node_9.output.nope }}', []))->toBeNull();
});

test('missing token resolves to configured empty string', function () {
    $resolver = (new ExpressionResolver)->withMissingValue('');

    expect($resolver->evaluate('{{ node_9.output.nope }}', []))->toBe('');
});

test('missing token inside interpolated string renders empty', function () {
    $resolver = (new ExpressionResolver)->withMissingValue('');

    expect($resolver->evaluate('Hi {{ node_9.output.name }}!', []))->toBe('Hi !');
});

// ── Non-string passthrough & array recursion in resolveConfig ─────────────────

test('resolveConfig leaves non-string scalars untouched and recurses arrays', function () {
    $config = [
        'to' => '{{ node_1.output.email }}',
        'retries' => 3,
        'enabled' => true,
        'ratio' => 1.5,
        'tags' => ['{{ node_1.output.tag }}', 'static'],
        'deep' => ['nested' => ['token' => '{{ node_1.output.city }}']],
    ];
    $context = ['node_1' => [
        'output' => ['email' => 'a@b.com', 'tag' => 'vip', 'city' => 'Berlin'],
    ]];

    $resolved = $this->resolver->resolveConfig(
        $this->resolver->compileConfig($config),
        $context,
    );

    expect($resolved['to'])->toBe('a@b.com')
        ->and($resolved['retries'])->toBe(3)
        ->and($resolved['enabled'])->toBeTrue()
        ->and($resolved['ratio'])->toBe(1.5)
        ->and($resolved['tags'])->toBe(['vip', 'static'])
        ->and($resolved['deep']['nested']['token'])->toBe('Berlin');
});

test('namespaced and bare-id forms resolve to the same value', function () {
    $context = [
        'node_2' => ['output' => ['city' => 'Berlin']],
        'nodes' => ['node_2' => ['output' => ['city' => 'Berlin']]],
    ];

    expect($this->resolver->evaluate('{{ node_2.output.city }}', $context))
        ->toBe($this->resolver->evaluate('{{ nodes.node_2.output.city }}', $context));
});
