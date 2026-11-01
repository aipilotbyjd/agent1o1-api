<?php

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('resolves a wait node via the public callback webhook', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();

    $workflow->replaceGraph(['nodes' => [['key' => 'w', 'type' => 'wait', 'config' => []]], 'edges' => []]);
    $workflow->publishVersion(publisher: $owner);
    $workflow = $workflow->fresh();

    $run = app(StartWorkflowRunAction::class)->execute($workflow);
    $token = $run->fresh(['nodeRuns'])->nodeRuns->first()->callback_token;

    $response = $this->postJson("/api/hooks/wait/{$token}", ['ok' => true]);

    $response->assertOk();
    expect($run->fresh()->status->value)->toBe('completed');
});

it('404s an unknown wait callback token', function () {
    $this->postJson('/api/hooks/wait/not-a-real-token', [])->assertNotFound();
});
