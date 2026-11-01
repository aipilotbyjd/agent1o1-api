<?php

use App\Ai\Tools\UseSkillTool;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request;

function agentWithSkill(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $skill = Skill::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Next Step Closer',
        'instructions' => 'End with a "Next step:" line.',
    ]);
    $skill->references()->create(['title' => 'Examples', 'content' => 'Next step: send the deck.', 'sort_order' => 0]);
    $agent->skills()->attach($skill->id);

    return [$owner, $agent->fresh()];
}

it('returns a skill\'s instructions and references by name', function () {
    [, $agent] = agentWithSkill();

    $result = (string) (new UseSkillTool($agent))->handle(new Request(['skill' => 'next step closer']));

    expect($result)
        ->toStartWith('# Skill: Next Step Closer')
        ->toContain('End with a "Next step:" line.')
        ->toContain("## Reference: Examples\nNext step: send the deck.");
});

it('names the available skills when asked for one the agent does not have', function () {
    [, $agent] = agentWithSkill();

    expect((string) (new UseSkillTool($agent))->handle(new Request(['skill' => 'Refunds'])))
        ->toBe('No skill named "Refunds". Your skills are: Next Step Closer.');
});

it('is only offered to agents with skills attached', function () {
    [$owner, $agent] = agentWithSkill();
    $bare = Agent::factory()->forWorkspace($agent->workspace)->create();
    $run = Run::factory()->create(['workspace_id' => $agent->workspace_id, 'triggered_by' => $owner->id]);

    $toolsFor = fn (Agent $agent) => collect(app(ToolRegistry::class)->toolsFor($agent, $run))
        ->filter(fn ($tool) => $tool instanceof UseSkillTool);

    expect($toolsFor($agent))->toHaveCount(1);
    expect($toolsFor($bare))->toBeEmpty();
});
