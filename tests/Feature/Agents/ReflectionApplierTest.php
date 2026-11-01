<?php

use App\Enums\Agents\ReflectionStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\Reflection;
use App\Models\Agents\ReflectionRun;
use App\Models\Agents\Skill;
use App\Models\User;
use App\Services\Agents\ReflectionApplier;
use App\Services\Workspaces\WorkspaceService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @return array{0: Agent, 1: User}
 */
function reflectionApplierAgent(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'Original instructions.']);

    return [$agent, $owner];
}

it('creates and attaches a new skill', function () {
    [$agent, $owner] = reflectionApplierAgent();
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create([
        'type' => 'new_skill',
        'title' => 'Refund handling',
        'proposed_prompt' => 'Always mention the 30 day window.',
    ]);

    $applied = app(ReflectionApplier::class)->apply($reflection, $owner);

    expect($applied->status)->toBe(ReflectionStatus::Applied);
    expect($applied->applied_run_id)->not->toBeNull();

    $skill = Skill::where('name', 'Refund handling')->sole();
    expect($skill->instructions)->toBe('Always mention the 30 day window.');
    expect($agent->skills()->whereKey($skill->id)->exists())->toBeTrue();
});

it('updates an existing skill and bumps its version', function () {
    [$agent] = reflectionApplierAgent();
    $skill = $agent->workspace->skills()->create([
        'name' => 'Old skill', 'slug' => 'old-skill', 'instructions' => 'Old instructions.', 'created_by' => null,
    ]);
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create([
        'type' => 'skill_fix',
        'target_skill_id' => $skill->id,
        'proposed_prompt' => 'New instructions.',
    ]);

    app(ReflectionApplier::class)->apply($reflection);

    expect($skill->fresh()->instructions)->toBe('New instructions.');
    expect($skill->fresh()->version)->toBe(2);
});

it('updates agent instructions and creates a new agent version', function () {
    [$agent] = reflectionApplierAgent();
    $versionsBefore = $agent->versions()->count();
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create([
        'type' => 'instruction_update',
        'proposed_prompt' => 'Updated instructions.',
    ]);

    app(ReflectionApplier::class)->apply($reflection);

    expect($agent->fresh()->instructions)->toBe('Updated instructions.');
    expect($agent->versions()->count())->toBeGreaterThan($versionsBefore);
});

it('marks a tool_access reflection applied without touching the agent or skills', function () {
    [$agent] = reflectionApplierAgent();
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create(['type' => 'tool_access']);

    $applied = app(ReflectionApplier::class)->apply($reflection);

    expect($applied->status)->toBe(ReflectionStatus::Applied);
    expect($applied->applied_run_id)->toBeNull();
    expect($agent->fresh()->instructions)->toBe('Original instructions.');
});

it('refuses to apply a reflection that is already resolved', function () {
    [$agent] = reflectionApplierAgent();
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create(['status' => 'dismissed']);

    expect(fn () => app(ReflectionApplier::class)->apply($reflection))
        ->toThrow(HttpException::class);
});

it('dismisses a pending reflection', function () {
    [$agent] = reflectionApplierAgent();
    $run = ReflectionRun::factory()->forAgent($agent)->create();
    $reflection = Reflection::factory()->forRun($run)->create();

    $dismissed = app(ReflectionApplier::class)->dismiss($reflection);

    expect($dismissed->status)->toBe(ReflectionStatus::Dismissed);
});
