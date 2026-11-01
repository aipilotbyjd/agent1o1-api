<?php

use App\Ai\Agents\AgentInstructionsAgent;
use App\Ai\Tools\SubmitAgentInstructionsTool;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

/**
 * @return array{0: string, 1: Agent}
 */
function improveInstructionsEndpoint(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create([
        'name' => 'Competitor Scout',
        'description' => 'Researches competitors.',
        'instructions' => 'Always cite sources.',
    ]);
    $agent->toolBindings()->create(['node_type' => 'call_api', 'config' => [], 'exposed_fields' => []]);

    Passport::actingAs($owner);

    return ["/api/v1/workspaces/{$workspace->id}/agents/{$agent->id}/instructions/improve", $agent];
}

it('rewrites instructions from the agent\'s purpose, tools and the requested change without saving them', function () {
    [$url, $agent] = improveInstructionsEndpoint();

    AgentInstructionsAgent::fake([new ToolCall('call_1', SubmitAgentInstructionsTool::NAME, [
        'instructions' => "  You are a competitor research analyst.\nAlways cite sources.  ",
    ])]);

    $this->postJson($url, ['request' => 'Focus on pricing'])
        ->assertOk()
        ->assertJsonPath('data.instructions', "You are a competitor research analyst.\nAlways cite sources.");

    AgentInstructionsAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Name: Competitor Scout')
        && $prompt->contains('Purpose: Researches competitors.')
        && $prompt->contains('Always cite sources.')
        && $prompt->contains('- call_api: Call API: Makes a generic outbound HTTP request')
        && $prompt->contains('Requested change: Focus on pricing'));

    expect($agent->fresh()->instructions)->toBe('Always cite sources.');
    expect(CreditTransaction::where('source_type', CreditTransactionType::AgentDraft)->sole()->credits)->toBeGreaterThan(0);
});

it('works for an agent that has no instructions yet', function () {
    [$url, $agent] = improveInstructionsEndpoint();
    $agent->update(['instructions' => null]);

    AgentInstructionsAgent::fake([new ToolCall('call_1', SubmitAgentInstructionsTool::NAME, ['instructions' => 'You are a researcher.'])]);

    $this->postJson($url)->assertOk()->assertJsonPath('data.instructions', 'You are a researcher.');

    AgentInstructionsAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("Current instructions:\n(none)"));
});

it('rewrites the unsaved instructions it is sent instead of the saved ones', function () {
    [$url] = improveInstructionsEndpoint();

    AgentInstructionsAgent::fake([new ToolCall('call_1', SubmitAgentInstructionsTool::NAME, ['instructions' => 'X'])]);

    $this->postJson($url, ['instructions' => 'Reply in French.'])->assertOk();

    AgentInstructionsAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("Current instructions:\nReply in French.")
        && ! $prompt->contains('Always cite sources.'));
});

it('refuses an agent from another workspace', function () {
    [, $agent] = improveInstructionsEndpoint();
    $other = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Other']);
    $foreign = Agent::factory()->forWorkspace($other)->create();

    $this->postJson("/api/v1/workspaces/{$agent->workspace_id}/agents/{$foreign->id}/instructions/improve")->assertNotFound();
});
