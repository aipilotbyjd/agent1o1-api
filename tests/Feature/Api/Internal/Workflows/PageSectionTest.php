<?php

use App\Enums\Workspaces\Role;
use App\Models\User;
use App\Models\Workflows\PageSection;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/page-sections";

    Passport::actingAs($this->owner);
});

it('saves a block to the library in its canonical shape', function () {
    $section = $this->postJson($this->base, [
        'name' => 'Pricing band',
        'block' => [
            'type' => 'section',
            'props' => ['background' => 'dark'],
            'children' => [
                ['type' => 'heading', 'props' => ['text' => 'Pricing']],
                ['type' => 'column', 'props' => []],
            ],
        ],
    ])->assertCreated()->json('data.section');

    expect($section['name'])->toBe('Pricing band');
    expect($section['block']['type'])->toBe('section');
    expect($section['block']['props']['background'])->toBe('dark');
    expect(array_column($section['block']['children'], 'type'))->toBe(['heading']);
});

it('refuses a lone column', function () {
    $this->postJson($this->base, ['name' => 'Col', 'block' => ['type' => 'column']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('block.type');
});

it('lists, renames and deletes only this workspace’s sections', function () {
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $mine = PageSection::factory()->forWorkspace($this->workspace)->create(['name' => 'Mine']);
    $theirs = PageSection::factory()->forWorkspace($other)->create(['name' => 'Theirs']);

    expect(collect($this->getJson($this->base)->assertOk()->json('data.sections'))->pluck('name')->all())->toBe(['Mine']);

    $this->patchJson("{$this->base}/{$mine->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.section.name', 'Renamed');
    $this->deleteJson("{$this->base}/{$theirs->id}")->assertNotFound();
    $this->deleteJson("{$this->base}/{$mine->id}")->assertNoContent();

    expect(PageSection::query()->count())->toBe(1);
});

it('keeps the library to people who build pages', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    $this->getJson($this->base)->assertForbidden();
});
