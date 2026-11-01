<?php

use App\Models\User;
use App\Services\Timezones\Timezones;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('maps every retired name to a current one', function () {
    foreach (Timezones::ALIASES as $retired => $current) {
        expect(Timezones::ids())->not->toContain($retired)->toContain($current);
    }
});

it('agrees with ICU on the current name for each retired one', function () {
    foreach (Timezones::ALIASES as $retired => $current) {
        expect(IntlTimeZone::getIanaID($retired))->toBe($current);
    }
})->skip(! method_exists(IntlTimeZone::class, 'getIanaID'), 'Needs ICU 74 or later.');

it('names a zone under its current name, and nothing that isn\'t one', function (mixed $zone, ?string $canonical) {
    expect(Timezones::canonical($zone))->toBe($canonical);
})->with([
    ['Asia/Kolkata', 'Asia/Kolkata'],
    ['Asia/Calcutta', 'Asia/Kolkata'],
    ['UTC', 'UTC'],
    ['Mars/Base', null],
    ['', null],
    [null, null],
]);

it('falls back to the default for an unknown zone', function () {
    expect(Timezones::resolve('Mars/Base'))->toBe('UTC')
        ->and(Timezones::resolve('Mars/Base', 'Asia/Kolkata'))->toBe('Asia/Kolkata');
});

it('lists the picker\'s timezones with their offsets', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/timezones')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => 'Asia/Kolkata', 'label' => 'Asia/Kolkata (UTC+05:30)'])
        ->assertJsonFragment(['id' => 'UTC', 'label' => 'UTC'])
        ->assertJsonMissing(['id' => 'Asia/Calcutta']);
});

it('only lists timezones to signed-in users', function () {
    $this->getJson('/api/v1/timezones')->assertUnauthorized();
});

it('starts a workspace in the timezone it was created with, under its current name', function () {
    $owner = User::factory()->create();
    Passport::actingAs($owner);

    $this->postJson('/api/v1/workspaces', ['name' => 'Acme', 'timezone' => 'Asia/Calcutta'])
        ->assertCreated()
        ->assertJsonPath('data.workspace.timezone', 'Asia/Kolkata');
});

it('stores a workspace timezone under its current name', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    Passport::actingAs($owner);

    $this->patchJson("/api/v1/workspaces/{$workspace->id}", ['timezone' => 'Asia/Calcutta'])
        ->assertSuccessful()
        ->assertJsonPath('data.workspace.timezone', 'Asia/Kolkata');

    expect($workspace->fresh()->timezone)->toBe('Asia/Kolkata');
});
