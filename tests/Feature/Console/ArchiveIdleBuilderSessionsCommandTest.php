<?php

use App\Enums\Workflows\BuilderSessionStatus;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Services\Workspaces\WorkspaceService;

it('archives idle, never-promoted sessions and leaves the rest', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $factory = WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner);

    $idle = $factory->create(['last_activity_at' => now()->subDays(31)]);
    $recent = $factory->create(['last_activity_at' => now()->subDays(2)]);
    $promoted = $factory->create([
        'last_activity_at' => now()->subDays(90),
        'workflow_id' => Workflow::factory()->forWorkspace($workspace)->create()->id,
        'status' => BuilderSessionStatus::Promoted,
    ]);

    $this->artisan('workflow-builder:archive-idle')->assertSuccessful();

    expect($idle->fresh()->status)->toBe(BuilderSessionStatus::Archived);
    expect($recent->fresh()->status)->toBe(BuilderSessionStatus::Active);
    expect($promoted->fresh()->status)->toBe(BuilderSessionStatus::Promoted);
});
