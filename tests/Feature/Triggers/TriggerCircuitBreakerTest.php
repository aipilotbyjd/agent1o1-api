<?php

use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function makeActiveTrigger(): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->webhook()->forWorkspace($workspace)->create();
}

it('disables a trigger once consecutive failures hit the configured threshold', function () {
    config(['triggers.failures_before_disable' => 3]);

    $trigger = makeActiveTrigger();

    $trigger->recordFailure();
    $trigger->recordFailure();
    expect($trigger->fresh()->is_active)->toBeTrue();

    $trigger->recordFailure();
    expect($trigger->fresh()->is_active)->toBeFalse();
    expect($trigger->fresh()->consecutive_failure_count)->toBe(3);
});

it('clears the failure streak on a success', function () {
    $trigger = makeActiveTrigger();

    $trigger->recordFailure();
    $trigger->recordFailure();
    expect($trigger->fresh()->consecutive_failure_count)->toBe(2);

    $trigger->recordSuccess();
    expect($trigger->fresh()->consecutive_failure_count)->toBe(0);
});
