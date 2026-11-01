<?php

use App\Services\Workflows\ConfigSchemaValidator;

it('passes a valid config against a node schema', function () {
    $validator = new ConfigSchemaValidator;

    $schema = [
        'type' => 'object',
        'required' => ['prompt'],
        'properties' => [
            'prompt' => ['type' => 'string'],
            'model' => ['type' => 'string'],
        ],
    ];

    expect($validator->validate($schema, ['prompt' => 'hello']))->toBe([]);
});

it('reports a missing required field', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['prompt'],
        'properties' => ['prompt' => ['type' => 'string']],
    ], []);

    expect($errors)->toBe(['config.prompt is required.']);
});

it('reports a wrong-typed field and an invalid enum value', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['method'],
        'properties' => [
            'method' => ['type' => 'string', 'enum' => ['GET', 'POST']],
            'timeout_seconds' => ['type' => 'integer'],
        ],
    ], ['method' => 'DELETE', 'timeout_seconds' => 'soon']);

    expect($errors)->toBe([
        'config.method must be one of: GET, POST.',
        'config.timeout_seconds must be an integer.',
    ]);
});

it('validates nested array items against their item schema', function () {
    $validator = new ConfigSchemaValidator;

    $errors = $validator->validate([
        'type' => 'object',
        'required' => ['operations'],
        'properties' => [
            'operations' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['op'],
                    'properties' => ['op' => ['type' => 'string']],
                ],
            ],
        ],
    ], ['operations' => [['op' => 'set'], []]]);

    expect($errors)->toBe(['config.operations[1].op is required.']);
});

it('accepts a whole {{ }} template for any field type until it is resolved', function () {
    $schema = [
        'type' => 'object',
        'properties' => [
            'limit' => ['type' => 'integer'],
            'enabled' => ['type' => 'boolean'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'method' => ['type' => 'string', 'enum' => ['GET', 'POST']],
        ],
    ];
    $config = [
        'limit' => '{{ nodes.a.count }}',
        'enabled' => '{{nodes.a.flag}}',
        'tags' => '{{ nodes.a.tags }}',
        'method' => '{{ input.method }}',
    ];

    expect((new ConfigSchemaValidator)->validate($schema, $config))->toBe([]);
    expect((new ConfigSchemaValidator)->validate($schema, $config, allowTemplates: false))->toHaveCount(4);
});

it('still rejects a template embedded in a non-string field', function () {
    $errors = (new ConfigSchemaValidator)->validate(
        ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]],
        ['limit' => '{{ nodes.a.count }}0'],
    );

    expect($errors)->toBe(['config.limit must be an integer.']);
});
