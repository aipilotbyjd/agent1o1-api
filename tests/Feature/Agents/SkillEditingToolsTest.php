<?php

use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\UpdateSkillTool;
use App\Enums\Workspaces\Role;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Skill;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\SkillInjector;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

function ownerAndAgent(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$owner, Agent::factory()->forWorkspace($workspace)->create(['allow_skill_editing' => true])];
}

it('creates a workspace skill as the person chatting and attaches it to the agent', function () {
    [$owner, $agent] = ownerAndAgent();

    $result = (string) (new CreateSkillTool($agent, $owner->id))->handle(new Request([
        'name' => 'Weekly Report',
        'description' => 'Format for the weekly sales report.',
        'instructions' => 'List revenue, pipeline and wins, in that order.',
    ]));

    $skill = Skill::query()->where('name', 'Weekly Report')->sole();
    expect($result)->toContain('Created the skill "Weekly Report"');
    expect($skill->workspace_id)->toBe($agent->workspace_id);
    expect($skill->created_by)->toBe($owner->id);
    expect($skill->description)->toBe('Format for the weekly sales report.');
    expect($agent->fresh()->skills->pluck('id')->all())->toBe([$skill->id]);
});

it('refuses a skill name that already exists in the workspace', function () {
    [$owner, $agent] = ownerAndAgent();
    Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Weekly Report']);

    $result = (string) (new CreateSkillTool($agent, $owner->id))->handle(new Request([
        'name' => 'weekly report',
        'description' => 'x',
        'instructions' => 'y',
    ]));

    expect($result)->toStartWith('Not created: a skill named "weekly report" already exists.');
    expect(Skill::query()->count())->toBe(1);
});

it('does not let someone who cannot manage skills create or edit one', function () {
    [, $agent] = ownerAndAgent();
    $viewer = User::factory()->create();
    $agent->workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    $skill = Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Tone', 'instructions' => 'Be brief.']);
    $agent->skills()->attach($skill->id);

    $created = (string) (new CreateSkillTool($agent, $viewer->id))->handle(new Request(['name' => 'New', 'description' => 'x', 'instructions' => 'y']));
    $updated = (string) (new UpdateSkillTool($agent->fresh(), $viewer->id))->handle(new Request(['skill' => 'Tone', 'instructions' => 'Be long.']));

    expect($created)->toStartWith('Not created: the person you are working for is not allowed');
    expect($updated)->toStartWith('Not updated: the person you are working for is not allowed');
    expect($skill->fresh()->instructions)->toBe('Be brief.');
});

it('updates an attached skill and bumps its version when the instructions change', function () {
    [$owner, $agent] = ownerAndAgent();
    $skill = Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Tone', 'instructions' => 'Sign off with "Best".', 'version' => 1]);
    $agent->skills()->attach($skill->id);

    $result = (string) (new UpdateSkillTool($agent->fresh(), $owner->id))->handle(new Request([
        'skill' => 'tone',
        'instructions' => 'Sign off with "Thanks".',
    ]));

    expect($result)->toBe('Updated the skill "Tone".');
    expect($skill->fresh()->instructions)->toBe('Sign off with "Thanks".');
    expect($skill->fresh()->version)->toBe(2);
});

it('will not update a skill other agents also use', function () {
    [$owner, $agent] = ownerAndAgent();
    $skill = Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Tone', 'instructions' => 'Be brief.', 'version' => 1]);
    $agent->skills()->attach($skill->id);
    Agent::factory()->forWorkspace($agent->workspace)->create()->skills()->attach($skill->id);

    $result = (string) (new UpdateSkillTool($agent->fresh(), $owner->id))->handle(new Request(['skill' => 'Tone', 'instructions' => 'Be long.']));

    expect($result)->toStartWith('Not updated: "Tone" is shared with other agents');
    expect($skill->fresh()->only(['instructions', 'version']))->toBe(['instructions' => 'Be brief.', 'version' => 1]);
});

it('will not update a skill that is not attached to the agent', function () {
    [$owner, $agent] = ownerAndAgent();
    Skill::factory()->create(['workspace_id' => $agent->workspace_id, 'name' => 'Elsewhere', 'instructions' => 'Keep.']);

    expect((string) (new UpdateSkillTool($agent, $owner->id))->handle(new Request(['skill' => 'Elsewhere', 'instructions' => 'Changed.'])))
        ->toBe('Not updated: you have no skill named "Elsewhere".');
});

it('offers the skill editing tools only in a conversation with skill editing on', function () {
    [$owner, $agent] = ownerAndAgent();
    $session = AgentSession::factory()->forAgent($agent)->create();
    $run = Run::factory()->create(['workspace_id' => $agent->workspace_id, 'triggered_by' => $owner->id]);

    $names = fn (Agent $agent, ?AgentSession $session) => collect(app(ToolRegistry::class)->toolsFor($agent, $run, $session))
        ->map(fn ($tool) => class_basename($tool))
        ->intersect(['CreateSkillTool', 'UpdateSkillTool'])
        ->values()
        ->all();

    expect($names($agent, $session))->toBe(['CreateSkillTool']);

    $agent->skills()->attach(Skill::factory()->create(['workspace_id' => $agent->workspace_id])->id);
    expect($names($agent->fresh(), $session))->toBe(['CreateSkillTool', 'UpdateSkillTool']);

    expect($names($agent->fresh(), null))->toBe([]);

    $agent->update(['allow_skill_editing' => false]);
    expect($names($agent->fresh(), $session))->toBe([]);
});

it('tells the agent it can save and fix skills only when skill editing is on', function () {
    [, $agent] = ownerAndAgent();

    expect(app(SkillInjector::class)->instructionsFor($agent))->toContain('`create_skill`');

    $agent->update(['allow_skill_editing' => false]);
    expect(app(SkillInjector::class)->instructionsFor($agent->fresh()))->not->toContain('`create_skill`');
});

it('caps an overlong description and refuses overlong instructions', function () {
    [$owner, $agent] = ownerAndAgent();
    $tool = new CreateSkillTool($agent, $owner->id);

    $result = (string) $tool->handle(new Request([
        'name' => 'Long One', 'description' => str_repeat('d', 800), 'instructions' => 'Do it.',
    ]));
    expect($result)->toContain('Created the skill')
        ->and(Skill::query()->where('name', 'Long One')->value('description'))->toHaveLength(Skill::DESCRIPTION_MAX_LENGTH);

    $result = (string) $tool->handle(new Request([
        'name' => 'Huge', 'description' => 'd', 'instructions' => str_repeat('i', Skill::INSTRUCTIONS_MAX_LENGTH + 1),
    ]));
    expect($result)->toStartWith('Not created')
        ->and(Skill::query()->where('name', 'Huge')->exists())->toBeFalse();
});
