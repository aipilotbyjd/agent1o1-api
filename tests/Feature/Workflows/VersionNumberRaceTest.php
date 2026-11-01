<?php

use App\Models\Agents\Agent;
use App\Models\Agents\AgentVersion;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowVersion;
use App\Services\Agents\AgentVersioner;
use App\Services\Workspaces\WorkspaceService;

/**
 * Both version tables carry a `unique({parent}_id, version)` constraint, and
 * both writers pick the next number with `max('version') + 1`. Two writers
 * racing for that number must not surface as a failed request: the loser
 * re-reads the watermark and takes the next one instead.
 *
 * The race is simulated by a one-shot `creating` hook that rewrites the
 * pending row's number to one already taken, which is exactly the state a
 * concurrent writer leaves behind between our watermark read and our insert.
 */
function collideWithVersionOnce(string $model, int $taken): void
{
    $collided = false;

    $model::creating(function ($version) use (&$collided, $taken): void {
        if ($collided) {
            return;
        }

        $collided = true;
        $version->version = $taken;
    });
}

afterEach(function () {
    WorkflowVersion::flushEventListeners();
    AgentVersion::flushEventListeners();
});

it('recovers when a concurrent publish takes the version number first', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [['key' => 'a', 'type' => 'transform', 'config' => ['mapping' => []]]],
        'edges' => [],
    ]);
    $workflow->publishVersion(publisher: $owner);

    collideWithVersionOnce(WorkflowVersion::class, taken: 1);

    $version = $workflow->fresh()->publishVersion(publisher: $owner);

    // The collision cost an attempt, not the request: the publish landed on
    // the next free number rather than dying on the unique constraint.
    expect($version->version)->toBe(2);
    expect($workflow->versions()->count())->toBe(2);
    expect($workflow->fresh()->current_version_id)->toBe($version->id);
});

it('recovers when a concurrent agent edit takes the version number first', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    // `AgentObserver` already minted version 1 when the agent was created.
    $agent = Agent::factory()->forWorkspace($workspace)->create();

    collideWithVersionOnce(AgentVersion::class, taken: 1);

    $version = app(AgentVersioner::class)->snapshot($agent->fresh(), $owner);

    expect($version->version)->toBe(2);
    expect($agent->versions()->count())->toBe(2);
});
