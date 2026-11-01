<?php

use App\Actions\Agents\CreateAgentSessionAction;
use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Agents\Skill;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->agent = Agent::factory()->forWorkspace($this->workspace)->create();
    $this->skill = Skill::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Cold email',
        'instructions' => 'Keep it under 80 words.',
    ]);
    $this->skill->references()->create(['title' => 'Tone', 'content' => 'Warm, never pushy.', 'sort_order' => 0]);
    $this->agent->skills()->attach($this->skill->id);
    $this->session = app(CreateAgentSessionAction::class)->execute($this->agent, $this->owner);
    $this->url = "/api/v1/workspaces/{$this->workspace->id}/agents/{$this->agent->id}/sessions/{$this->session->id}/messages";

    Passport::actingAs($this->owner);
});

it('adds a picked skill\'s full instructions to that turn and records it on the message', function () {
    WorkspaceAgent::fake(['Hi Dana, …']);

    $this->postJson($this->url, ['message' => 'Write one for Acme', 'skill_id' => $this->skill->id])->assertOk();

    WorkspaceAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'Skill chosen for this request')
        && str_contains($prompt->agent->instructions(), 'Keep it under 80 words.')
        && str_contains($prompt->agent->instructions(), 'Warm, never pushy.'));

    expect($this->session->messages()->where('role', 'user')->sole()->skill_id)->toBe($this->skill->id);
    expect(Run::query()->where('runnable_id', $this->session->id)->sole()->agent_context['skill'])->toBe('Cold email');

    $this->getJson("{$this->url}")
        ->assertOk()
        ->assertJsonPath('data.0.skill.name', 'Cold email');
});

it('leaves the prompt alone when no skill is picked', function () {
    WorkspaceAgent::fake(['Hello.']);

    $this->postJson($this->url, ['message' => 'Hi'])->assertOk();

    WorkspaceAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains($prompt->agent->instructions(), 'Skill chosen for this request'));
});

it('refuses a skill that is not attached to the agent', function () {
    WorkspaceAgent::fake(['Hello.']);
    $other = Skill::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Other']);

    $this->postJson($this->url, ['message' => 'Hi', 'skill_id' => $other->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('skill_id');

    expect($this->session->messages()->count())->toBe(0);
});
