<?php

use App\Exceptions\InvalidNodeInputException;
use App\Services\Workflows\ConfigSchemaValidator;
use App\Services\Workflows\ResolvedConfigCaster;

function caster(): ResolvedConfigCaster
{
    return new ResolvedConfigCaster(new ConfigSchemaValidator);
}

$schema = [
    'type' => 'object',
    'required' => ['channel'],
    'properties' => [
        'channel' => ['type' => 'string'],
        'limit' => ['type' => 'integer'],
        'unfurl' => ['type' => 'boolean'],
        'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
        'headers' => ['type' => 'object'],
    ],
];

it('converts templated values to the type the schema declares', function () use ($schema) {
    $raw = [
        'channel' => '{{ nodes.a.channel }}',
        'limit' => '{{ nodes.a.limit }}',
        'unfurl' => '{{ nodes.a.unfurl }}',
        'labels' => '{{ nodes.a.labels }}',
        'headers' => '{{ nodes.a.headers }}',
    ];
    $resolved = ['channel' => 42, 'limit' => '10', 'unfurl' => 'true', 'labels' => '["x","y"]', 'headers' => '{"X-Id":"1"}'];

    expect(caster()->cast($schema, $raw, $resolved))->toBe([
        'channel' => '42',
        'limit' => 10,
        'unfurl' => true,
        'labels' => ['x', 'y'],
        'headers' => ['X-Id' => '1'],
    ]);
});

it('wraps a single templated value into a list field', function () use ($schema) {
    $config = caster()->cast($schema, ['channel' => 'c', 'labels' => '{{ nodes.a.label }}'], ['channel' => 'c', 'labels' => 'urgent']);

    expect($config['labels'])->toBe(['urgent']);
});

it('leaves literal values exactly as configured', function () use ($schema) {
    $config = ['channel' => 'general', 'limit' => 5];

    expect(caster()->cast($schema, $config, $config))->toBe($config);
});

it('drops an optional templated field that resolved to nothing so the node default applies', function () use ($schema) {
    $config = caster()->cast($schema, ['channel' => 'c', 'limit' => '{{ nodes.a.missing }}'], ['channel' => 'c', 'limit' => null]);

    expect($config)->toBe(['channel' => 'c']);
});

it('reports a required templated field that resolved to nothing', function () use ($schema) {
    caster()->cast($schema, ['channel' => '{{ nodes.a.missing }}'], ['channel' => null]);
})->throws(InvalidNodeInputException::class, 'config.channel is required.');

it('reports a templated value that cannot be converted', function () use ($schema) {
    caster()->cast($schema, ['channel' => 'c', 'limit' => '{{ nodes.a.limit }}'], ['channel' => 'c', 'limit' => 'lots']);
})->throws(InvalidNodeInputException::class, 'config.limit must be an integer.');
