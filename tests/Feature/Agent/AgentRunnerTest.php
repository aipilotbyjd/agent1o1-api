<?php

use App\Agents\AgentRunner;
use App\Agents\Tools\ListSkillsTool;
use App\Agents\Tools\LoadSkillTool;
use App\Agents\Tools\SkillScriptTool;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, [
        'id' => Str::uuid()->toString(),
        'role' => Role::Owner,
        'joined_at' => now(),
    ]);

    $this->agent = Agent::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
});

test('the system prompt only contains a skill catalog, not full instructions', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'name' => 'Refund Policy',
        'description' => 'Explains the refund policy to customers.',
        'instructions' => 'THIS SECRET INSTRUCTION TEXT SHOULD NOT APPEAR IN THE PROMPT DIRECTLY.',
    ]);
    $this->agent->skills()->attach($skill->id);

    $workflowAgent = (new AgentRunner)->build($this->agent, 'hello');
    $prompt = (string) $workflowAgent->instructions();

    expect($prompt)->toContain($skill->slug)
        ->toContain('Explains the refund policy to customers.')
        ->toContain('load_skill_tool')
        ->not->toContain('THIS SECRET INSTRUCTION TEXT');
});

test('list_skills_tool returns the catalog', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $this->agent->skills()->attach($skill->id);

    $workflowAgent = (new AgentRunner)->build($this->agent, 'hello');
    $listTool = collect($workflowAgent->tools())->first(fn ($t) => $t instanceof ListSkillsTool);

    $result = json_decode((string) $listTool->handle(new Request), true);

    expect($result)->toHaveCount(1)
        ->and($result[0]['slug'])->toBe($skill->slug)
        ->and($result[0]['name'])->toBe($skill->name);
});

test('load_skill_tool returns full instructions and references for a valid slug', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
        'instructions' => 'Full detailed instructions here.',
    ]);
    $skill->references()->create(['title' => 'Doc 1', 'content' => 'Reference body.', 'sort_order' => 0]);
    $this->agent->skills()->attach($skill->id);

    $workflowAgent = (new AgentRunner)->build($this->agent, 'hello');
    $loadTool = collect($workflowAgent->tools())->first(fn ($t) => $t instanceof LoadSkillTool);

    $result = (string) $loadTool->handle(new Request(['skill_slug' => $skill->slug]));

    expect($result)->toContain('Full detailed instructions here.')
        ->toContain('Doc 1')
        ->toContain('Reference body.');
});

test('load_skill_tool errors on an unknown slug', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $this->agent->skills()->attach($skill->id);

    $workflowAgent = (new AgentRunner)->build($this->agent, 'hello');
    $loadTool = collect($workflowAgent->tools())->first(fn ($t) => $t instanceof LoadSkillTool);

    $result = (string) $loadTool->handle(new Request(['skill_slug' => 'not-a-real-skill']));

    expect($result)->toContain('is not attached to this agent');
});

test('script tools are registered for all attached skills, not a filtered subset', function () {
    $skills = AgentSkill::factory()->count(6)->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);

    foreach ($skills as $skill) {
        $this->agent->skills()->attach($skill->id);
        $skill->scripts()->create([
            'name' => 'do-thing',
            'description' => 'Does a thing.',
            'language' => 'javascript',
            'code' => 'console.log("ok")',
            'is_enabled' => true,
        ]);
    }

    $workflowAgent = (new AgentRunner)->build($this->agent, 'irrelevant message with no keyword overlap');
    $scriptTools = collect($workflowAgent->tools())->filter(fn ($t) => $t instanceof SkillScriptTool);

    expect($scriptTools)->toHaveCount(6);
});

test('skill script execution times out cleanly instead of hanging', function () {
    $skill = AgentSkill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->user->id,
    ]);
    $script = $skill->scripts()->create([
        'name' => 'sleeper',
        'description' => 'Sleeps forever.',
        'language' => 'javascript',
        'code' => 'setTimeout(() => {}, 60000);',
        'is_enabled' => true,
    ]);

    $tool = new SkillScriptTool($script);
    $result = json_decode((string) $tool->handle(new Request(['input' => '{}'])), true);

    expect($result['error'] ?? null)->toContain('timed out');
});
