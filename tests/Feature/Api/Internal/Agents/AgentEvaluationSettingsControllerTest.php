<?php

use App\Models\Agents\Agent;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

it('shows default settings and updates criteria, tags and data points', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);
    $base = "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/evaluation-settings";

    $this->getJson($base)->assertOk()->assertJsonPath('data.settings.is_enabled', false);

    $response = $this->patchJson($base, [
        'is_enabled' => true,
        'sentiment_affects_grade' => true,
        'criteria' => [
            ['name' => 'Accuracy', 'prompt' => 'Correct info', 'type' => 'other', 'priority' => 'notify'],
        ],
        'tags' => [
            ['name' => 'ESCALATION_NEEDED', 'description' => 'User asked for a human.'],
        ],
        'data_points' => [
            ['name' => 'Resolution Status', 'data_type' => 'string', 'description' => 'resolved or unresolved'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.settings.is_enabled', true)
        ->assertJsonPath('data.settings.criteria.0.name', 'Accuracy')
        ->assertJsonPath('data.settings.tags.0.name', 'ESCALATION_NEEDED')
        ->assertJsonPath('data.settings.data_points.0.name', 'Resolution Status');

    expect($response->json('data.settings.criteria.0.id'))->not->toBeNull();
});

it('rejects more criteria than the configured limit', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    Passport::actingAs($owner);

    $criteria = array_fill(0, 31, ['name' => 'x', 'prompt' => 'x', 'type' => 'other', 'priority' => 'flag']);

    $this->patchJson(
        "/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/evaluation-settings",
        ['criteria' => $criteria],
    )->assertStatus(422);
});
