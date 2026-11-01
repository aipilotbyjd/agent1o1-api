<?php

use App\Ai\Tools\RememberTool;
use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

it('creates a new memory scoped to the given user', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $tool = new RememberTool($agent, $owner->id);
    $tool->handle(new Request(['key' => 'favorite_color', 'value' => 'blue']));

    $memory = $agent->memories()->sole();
    expect($memory->key)->toBe('favorite_color');
    expect($memory->value)->toBe('blue');
    expect($memory->type)->toBe('fact');
    expect($memory->user_id)->toBe($owner->id);
});

it('creates a workspace-wide memory when no user is given', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $tool = new RememberTool($agent);
    $tool->handle(new Request(['key' => 'company_name', 'value' => 'Acme Inc']));

    expect($agent->memories()->sole()->user_id)->toBeNull();
});

it('updates the existing memory instead of duplicating it when the key is re-saved', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $tool = new RememberTool($agent, $owner->id);
    $tool->handle(new Request(['key' => 'favorite_color', 'value' => 'blue']));
    $tool->handle(new Request(['key' => 'favorite_color', 'value' => 'green', 'type' => 'preference']));

    expect($agent->memories()->count())->toBe(1);
    $memory = $agent->memories()->sole();
    expect($memory->value)->toBe('green');
    expect($memory->type)->toBe('preference');
});

it('does not collide across different users for the same key', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    (new RememberTool($agent, $owner->id))->handle(new Request(['key' => 'favorite_color', 'value' => 'blue']));
    (new RememberTool($agent, $other->id))->handle(new Request(['key' => 'favorite_color', 'value' => 'green']));

    expect($agent->memories()->count())->toBe(2);
});

it('is exposed to the model as `remember` and steers personal facts to itself', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $tool = new RememberTool($agent, $owner->id);

    expect($tool->name())->toBe('remember');
    expect((string) $tool->description())->toContain('what to call them');
});
