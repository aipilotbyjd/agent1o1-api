<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

it('stores and reads back per-agent memories, scoped per user when set', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    $agent->memories()->create(['key' => 'favorite_color', 'value' => 'blue', 'user_id' => $owner->id]);
    $agent->memories()->create(['key' => 'favorite_color', 'value' => 'green', 'user_id' => $other->id]);
    $agent->memories()->create(['key' => 'company_name', 'value' => 'Acme Inc']);

    expect($agent->memories()->count())->toBe(3);
    expect($agent->memories()->where('user_id', $owner->id)->sole()->value)->toBe('blue');
    expect($agent->memories()->whereNull('user_id')->sole()->type)->toBe('fact');
});
