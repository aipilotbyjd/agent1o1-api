<?php

use App\Ai\Agents\AgentDraftAgent;
use App\Ai\Tools\SubmitAgentDraftTool;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Ai\ModelCatalog;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

/**
 * @return array{0: string, 1: ModelCatalog}
 */
function draftEndpoint(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $catalog = ModelCatalog::factory()->create(['slug' => 'draft-model']);
    $catalog->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-test', 'priority' => 0]);

    Passport::actingAs($owner);

    return ["/api/v1/workspaces/{$workspace->id}/agents/draft", $catalog];
}

it('drafts an agent from a description without saving it', function () {
    [$url, $catalog] = draftEndpoint();

    AgentDraftAgent::fake([new ToolCall('call_1', SubmitAgentDraftTool::NAME, [
        'name' => 'Ticket Theme Analyzer',
        'description' => 'Groups support tickets by theme.',
        'instructions' => 'You are a support ticket analyzer.',
        'icon' => 'headphones',
        'color' => 'green',
    ])]);

    $this->postJson($url, ['prompt' => 'Summarize our support tickets by theme', 'model_catalog_id' => $catalog->id])
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Success',
            'data' => ['draft' => [
                'name' => 'Ticket Theme Analyzer',
                'description' => 'Groups support tickets by theme.',
                'instructions' => 'You are a support ticket analyzer.',
                'icon' => 'headphones',
                'color' => 'green',
            ]],
        ]);

    AgentDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Summarize our support tickets by theme'));
    $this->assertDatabaseCount('agents', 0);
    expect(CreditTransaction::where('source_type', CreditTransactionType::AgentDraft)->sole()->credits)->toBeGreaterThan(0);
});

it('falls back to a default icon and color the frontend can render', function () {
    [$url, $catalog] = draftEndpoint();

    AgentDraftAgent::fake([new ToolCall('call_1', SubmitAgentDraftTool::NAME, [
        'name' => 'X', 'description' => 'Y', 'instructions' => 'Z', 'icon' => 'unicorn', 'color' => '#123456',
    ])]);

    $this->postJson($url, ['prompt' => 'Anything', 'model_catalog_id' => $catalog->id])
        ->assertOk()
        ->assertJsonPath('data.draft.icon', 'bot')
        ->assertJsonPath('data.draft.color', 'purple');
});

it('validates the prompt and model', function () {
    [$url] = draftEndpoint();

    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'model_catalog_id']);
});

it('answers with a clear error, and charges nothing, when the model does not submit a draft', function () {
    [$url, $catalog] = draftEndpoint();

    AgentDraftAgent::fake(['Here is your agent: ...']);

    $this->postJson($url, ['prompt' => 'Anything', 'model_catalog_id' => $catalog->id])
        ->assertStatus(502)
        ->assertJsonPath('message', "The model didn't return a usable answer. Try again, or pick a different model.");

    expect(CreditTransaction::query()->count())->toBe(0);
});
