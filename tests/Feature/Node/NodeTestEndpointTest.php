<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);
});

function testNode(array $body)
{
    return test()->actingAs(test()->user, 'api')
        ->postJson("/api/v1/workspaces/".test()->workspace->id."/workflows/test-node", $body);
}

test('testing a node runs it for real and returns its output, input and duration', function () {
    $response = testNode([
        'node_type' => 'set_variable',
        'parameters' => ['key' => 'greeting', 'value' => 'hi', 'scope' => 'execution'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.success', true)
        ->assertJsonPath('data.output.key', 'greeting')
        ->assertJsonPath('data.output.value', 'hi')
        ->assertJsonPath('data.input.config.key', 'greeting');

    expect($response->json('data.duration'))->toBeInt();
});

test('a node test resolves expressions against provided upstream output', function () {
    $response = testNode([
        'node_type' => 'data.transform',
        'parameters' => ['mappings' => ['greeting' => 'Hello {{ node_5.output.name }}']],
        'input' => ['node_5' => ['name' => 'jaydeep']],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.success', true)
        ->assertJsonPath('data.output.greeting', 'Hello jaydeep');
});

test('testing a node with no handler returns a real error, not a fake success', function () {
    testNode(['node_type' => 'does.not.exist', 'parameters' => []])
        ->assertOk()
        ->assertJsonPath('data.success', false)
        ->assertJsonPath('data.error', 'No handler registered for node type: does.not.exist');
});

test('a node that fails validation inside its handler reports the real failure', function () {
    // SetVariable requires a key; omitting it makes the handler fail for real.
    testNode(['node_type' => 'set_variable', 'parameters' => ['scope' => 'execution']])
        ->assertOk()
        ->assertJsonPath('data.success', false)
        ->assertJsonPath('data.error', 'Variable key is required');
});

test('node_type is required', function () {
    testNode(['parameters' => []])->assertStatus(422);
});
