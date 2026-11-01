<?php

use App\Models\System\SetupSync;

beforeEach(function () {
    $this->headers = superAdminHeaders(superAdmin());
});

it('reports the setup data as down until it has been synced', function () {
    $check = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->assertOk()->json('data.checks.setup_data');

    expect($check['status'])->toBe('down')
        ->and($check['message'])->toContain('setup:catalog:sync')
        ->and($check['details']['node-categories'])->toBe('never synced');
});

it('reports the setup data as healthy once everything is in sync', function () {
    $this->syncSetup();

    $check = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->json('data.checks.setup_data');

    expect($check['status'])->toBe('healthy')
        ->and($check['details'])->each->toBe('in sync');
});

it('reports a dataset that changed in code as degraded', function () {
    $this->syncSetup();

    SetupSync::query()->where('dataset', 'plans')->update(['checksum' => str_repeat('0', 40)]);

    $check = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->json('data.checks.setup_data');

    expect($check['status'])->toBe('degraded')
        ->and($check['details']['plans'])->toBe('out of date');
});

it('reports a failed dataset as down', function () {
    $this->syncSetup();

    SetupSync::query()->where('dataset', 'connectors')->update(['status' => 'failed', 'error' => 'boom']);

    $check = $this->withHeaders($this->headers)->getJson(saUrl('system/health'))->json('data.checks.setup_data');

    expect($check['status'])->toBe('down')
        ->and($check['details']['connectors'])->toBe('failed');
});
