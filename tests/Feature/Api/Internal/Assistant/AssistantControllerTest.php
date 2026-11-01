<?php

use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->addMember = function (Role $role): User {
        $user = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);

        return $user;
    };
});

it('provisions the assistant on first open and returns the brand', function () {
    config(['assistant.brand.name' => 'Nimbus']);
    Passport::actingAs($this->owner);

    $this->getJson($this->url)
        ->assertSuccessful()
        ->assertJsonPath('data.assistant.user_id', $this->owner->id)
        ->assertJsonPath('data.brand.name', 'Nimbus');

    $this->getJson($this->url)->assertSuccessful();

    expect(Assistant::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('returns each member their own assistant', function () {
    $member = ($this->addMember)(Role::Member);
    $ownerAssistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);

    Passport::actingAs($member);

    $response = $this->getJson($this->url)->assertSuccessful();

    expect($response->json('data.assistant.id'))->not->toBe($ownerAssistant->id)
        ->and($response->json('data.assistant.user_id'))->toBe($member->id);
});

it('is not available to viewers', function () {
    Passport::actingAs(($this->addMember)(Role::Viewer));

    $this->getJson($this->url)->assertForbidden();
});

it('is not available outside the workspace', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson($this->url)->assertForbidden();
});

it('updates the standing instructions', function () {
    Passport::actingAs($this->owner);

    $this->patchJson($this->url, ['instructions' => 'Keep it short.'])
        ->assertSuccessful()
        ->assertJsonPath('data.assistant.instructions', 'Keep it short.');
});

it('rejects instructions over the limit', function () {
    Passport::actingAs($this->owner);

    $this->patchJson($this->url, ['instructions' => str_repeat('a', config('assistant.limits.instructions_max_chars') + 1)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('instructions');
});
