<?php

use App\Services\Workflows\NodeOptions\NodeOptionsService;
use App\Services\Workflows\NodeRegistry;

it('gives every visible field of every node a title and an editor widget', function () {
    foreach (app(NodeRegistry::class)->placeableTypes() as $node) {
        foreach ($node['config_schema']['properties'] ?? [] as $key => $field) {
            if ($field['x-hidden'] ?? false) {
                continue;
            }

            expect($field)->toHaveKey('title', message: "{$node['type']}.{$key} has no title")
                ->and($field)->toHaveKey('x-widget', message: "{$node['type']}.{$key} has no x-widget");
        }
    }
});

it('points every dynamic field at a registered options source and real dependencies', function () {
    $sources = app(NodeOptionsService::class)->sourceKeys();
    $checked = 0;

    foreach (app(NodeRegistry::class)->placeableTypes() as $node) {
        $properties = $node['config_schema']['properties'] ?? [];

        foreach ($properties as $key => $field) {
            if (! isset($field['x-options'])) {
                continue;
            }

            $checked++;
            expect($sources)->toContain($field['x-options']['source']);

            foreach ([...$field['x-options']['depends_on'], ...$field['x-options']['uses']] as $dependency) {
                expect($properties)->toHaveKey($dependency, message: "{$node['type']}.{$key} depends on missing field {$dependency}");
            }
        }
    }

    expect($checked)->toBeGreaterThan(30);
});

it('starts every connected-app node with an account picker and hides the raw token', function () {
    $registry = app(NodeRegistry::class);

    foreach ($registry->catalog() as $node) {
        if (! $node['requires_connector']) {
            continue;
        }

        $properties = $node['config_schema']['properties'];

        expect(array_key_first($properties))->toBe('credential_id')
            ->and($properties['credential_id']['x-widget'])->toBe('credential')
            ->and($properties['credential_id']['x-connector'])->toBe($node['category'])
            ->and($properties['access_token']['x-hidden'])->toBeTrue();
    }
});

it('offers spreadsheet and tab dropdowns on the google sheets nodes', function () {
    $properties = app(NodeRegistry::class)->configSchemaFor('google_sheets_get_values')['properties'];

    expect($properties['spreadsheet_id']['x-options']['source'])->toBe('google_sheets.spreadsheets')
        ->and($properties['range']['x-options'])->toMatchArray([
            'source' => 'google_sheets.sheets',
            'depends_on' => ['spreadsheet_id'],
            'allow_custom' => true,
        ]);
});

it('labels fixed choices', function () {
    $operator = app(NodeRegistry::class)->configSchemaFor('filter')['properties']['operator'];

    expect($operator['enum'])->toContain('not_equals')
        ->and($operator['x-enum-labels']['not_equals'])->toBe('Does not equal');
});

it('has a node field for every registered options source', function () {
    $used = collect(app(NodeRegistry::class)->placeableTypes())
        ->flatMap(fn (array $node): array => array_column($node['config_schema']['properties'] ?? [], 'x-options'))
        ->pluck('source')
        ->unique();

    expect(array_values(array_diff(app(NodeOptionsService::class)->sourceKeys(), $used->all())))->toBe([]);
});
