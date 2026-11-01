<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Passport\Passport;

/*
 * Drives the real `/api/broadcasting/auth` endpoint, so the closures in
 * `routes/channels.php` run too: `WorkspaceChannelGate` alone is covered by
 * RunBroadcastingTest. The `reverb` driver is the one that actually evaluates
 * channel callbacks; the default test driver does not.
 */
function authorizeChannel($test, string $channel, string $type = 'private')
{
    // Switched on per request, after test data exists: creating a run
    // broadcasts, and a real reverb driver would try to reach a server.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    // Channels register on whichever driver is default at boot (`null` under
    // test), so rebuild the driver and register them on it again.
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');

    return $test->postJson('/api/broadcasting/auth', [
        'channel_name' => "{$type}-{$channel}",
        'socket_id' => '1234.5678',
    ]);
}

function runIn($workspace, User $publisher)
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $publisher);

    return app(StartWorkflowRunAction::class)->execute($workflow->fresh());
}

it('lets a member subscribe to their workspace run channels and refuses outsiders', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $run = runIn($workspace, $owner);

    Passport::actingAs($owner);
    authorizeChannel($this, "workspaces.{$workspace->id}.runs")->assertOk();
    authorizeChannel($this, "workspaces.{$workspace->id}.runs.{$run->id}")->assertOk();

    Passport::actingAs($stranger);
    authorizeChannel($this, "workspaces.{$workspace->id}.runs")->assertForbidden();
    authorizeChannel($this, "workspaces.{$workspace->id}.runs.{$run->id}")->assertForbidden();
});

it('refuses a run channel that splices another workspaces run into your own workspace', function () {
    $owner = User::factory()->create();
    $mine = app(WorkspaceService::class)->create($owner, ['name' => 'Mine']);
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Theirs']);
    $theirRun = runIn($other, $other->owner);

    Passport::actingAs($owner);

    authorizeChannel($this, "workspaces.{$mine->id}.runs.{$theirRun->id}")->assertForbidden();
    authorizeChannel($this, "workspaces.{$other->id}.runs.{$theirRun->id}")->assertForbidden();
});

it('lets a member subscribe to an agent session channel and refuses outsiders', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $session = AgentSession::factory()->forAgent(Agent::factory()->forWorkspace($workspace)->create())->create();

    Passport::actingAs($owner);
    authorizeChannel($this, "workspaces.{$workspace->id}.agent-sessions.{$session->id}")->assertOk();

    Passport::actingAs($stranger);
    authorizeChannel($this, "workspaces.{$workspace->id}.agent-sessions.{$session->id}")->assertForbidden();
});

it('only lets a user subscribe to their own private channel', function () {
    // Ids that start with a letter all cast to 0 as integers, so a loose
    // comparison would treat these two different users as the same one.
    $alice = User::factory()->create(['id' => 'a0000000-0000-7000-8000-000000000001']);
    $bob = User::factory()->create(['id' => 'b0000000-0000-7000-8000-000000000002']);

    Passport::actingAs($alice);

    authorizeChannel($this, "App.Models.User.{$alice->id}")->assertOk();
    authorizeChannel($this, "App.Models.User.{$bob->id}")->assertForbidden();
});

it('lets a member join a workflow presence channel as themselves and refuses outsiders', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace', 'avatar' => null]);
    $stranger = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $channel = "workspaces.{$workspace->id}.workflows.{$workflow->id}";

    Passport::actingAs($owner);
    $response = authorizeChannel($this, $channel, 'presence')->assertOk();

    expect(json_decode($response->json('channel_data'), true)['user_info'])
        ->toBe(['id' => $owner->id, 'name' => 'Ada Lovelace', 'avatar' => null]);

    Passport::actingAs($stranger);
    authorizeChannel($this, $channel, 'presence')->assertForbidden();
});

it('refuses presence in a workflow that belongs to another workspace', function () {
    $owner = User::factory()->create();
    $mine = app(WorkspaceService::class)->create($owner, ['name' => 'Mine']);
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Theirs']);
    $theirs = Workflow::factory()->forWorkspace($other)->create();

    Passport::actingAs($owner);

    authorizeChannel($this, "workspaces.{$mine->id}.workflows.{$theirs->id}", 'presence')->assertForbidden();
});
