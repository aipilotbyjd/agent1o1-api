<?php

use App\Ai\Agents\SkillDraftAgent;
use App\Ai\Tools\SubmitSkillDraftTool;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workspaces\Role;
use App\Models\Ai\ModelCatalog;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

/**
 * @return array{0: string, 1: ModelCatalog, 2: Workspace}
 */
function skillDraftEndpoint(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $catalog = ModelCatalog::factory()->create(['slug' => 'draft-model']);
    $catalog->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-test', 'priority' => 0]);

    Passport::actingAs($owner);

    return ["/api/v1/workspaces/{$workspace->id}/skills/draft", $catalog, $workspace];
}

it('drafts a skill with references and scripts without saving it', function () {
    [$url, $catalog] = skillDraftEndpoint();

    SkillDraftAgent::fake([new ToolCall('call_1', SubmitSkillDraftTool::NAME, [
        'name' => 'Weekly MRR Report',
        'description' => 'Builds the weekly MRR report. Load it when asked for revenue updates.',
        'category' => 'Data',
        'icon' => 'Zap',
        'color' => '#0EA5E9',
        'tags' => ['finance', 'weekly'],
        'instructions' => '1. Pull subscriptions. 2. Fill the "Report template".',
        'references' => [['title' => 'Report template', 'content' => '## MRR: {total}']],
        'scripts' => [[
            'name' => 'compute_mrr',
            'description' => 'Sums monthly amounts.',
            'language' => 'python',
            'code' => 'print(sum(amounts))',
        ]],
    ])]);

    $this->postJson($url, ['prompt' => 'Our weekly MRR report process', 'model_catalog_id' => $catalog->id])
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'statusCode' => 200,
            'message' => 'Success',
            'data' => ['draft' => [
                'name' => 'Weekly MRR Report',
                'description' => 'Builds the weekly MRR report. Load it when asked for revenue updates.',
                'category' => 'Data',
                'icon' => 'Zap',
                'color' => '#0EA5E9',
                'tags' => ['finance', 'weekly'],
                'instructions' => '1. Pull subscriptions. 2. Fill the "Report template".',
                'references' => [['title' => 'Report template', 'content' => '## MRR: {total}']],
                'scripts' => [[
                    'name' => 'compute_mrr',
                    'description' => 'Sums monthly amounts.',
                    'language' => 'python',
                    'code' => 'print(sum(amounts))',
                ]],
            ]],
        ]);

    SkillDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Our weekly MRR report process'));
    $this->assertDatabaseCount('skills', 0);
    expect(CreditTransaction::where('source_type', CreditTransactionType::SkillDraft)->sole()->credits)->toBeGreaterThan(0);
});

it('falls back to a category and look the Skills page offers, and drops references and scripts it would reject', function () {
    [$url, $catalog] = skillDraftEndpoint();

    SkillDraftAgent::fake([new ToolCall('call_1', SubmitSkillDraftTool::NAME, [
        'name' => 'X',
        'description' => 'Y',
        'category' => 'Reporting',
        'icon' => 'unicorn',
        'color' => '#123456',
        'tags' => ['a', 'a', '', 'b'],
        'instructions' => 'Z',
        'references' => [['title' => 'Empty', 'content' => '  '], ['title' => 'Kept', 'content' => 'Body']],
        'scripts' => [
            ['name' => 'ruby_script', 'description' => '', 'language' => 'ruby', 'code' => 'puts 1'],
            ['name' => 'kept', 'description' => '', 'language' => 'bash', 'code' => 'echo 1'],
        ],
    ])]);

    $this->postJson($url, ['prompt' => 'Anything', 'model_catalog_id' => $catalog->id])
        ->assertOk()
        ->assertJsonPath('data.draft.category', 'General')
        ->assertJsonPath('data.draft.icon', 'Puzzle')
        ->assertJsonPath('data.draft.color', '#6366F1')
        ->assertJsonPath('data.draft.tags', ['a', 'b'])
        ->assertJsonPath('data.draft.references', [['title' => 'Kept', 'content' => 'Body']])
        ->assertJsonPath('data.draft.scripts.0.name', 'kept')
        ->assertJsonCount(1, 'data.draft.scripts');
});

it('validates the prompt and model', function () {
    [$url] = skillDraftEndpoint();

    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['prompt', 'model_catalog_id']);
});

it('does not let a viewer draft a skill', function () {
    [$url, $catalog, $workspace] = skillDraftEndpoint();
    $viewer = User::factory()->create();
    $workspace->members()->create(['user_id' => $viewer->id, 'role' => Role::Viewer, 'joined_at' => now()]);
    Passport::actingAs($viewer);

    SkillDraftAgent::fake()->preventStrayPrompts();

    $this->postJson($url, ['prompt' => 'Anything', 'model_catalog_id' => $catalog->id])->assertForbidden();
});

it('answers with a clear error, and charges nothing, when the model does not submit a draft', function () {
    [$url, $catalog] = skillDraftEndpoint();

    SkillDraftAgent::fake(['Here is your skill: ...']);

    $this->postJson($url, ['prompt' => 'Anything', 'model_catalog_id' => $catalog->id])
        ->assertStatus(502)
        ->assertJsonPath('message', "The model didn't return a usable answer. Try again, or pick a different model.");

    expect(CreditTransaction::query()->count())->toBe(0);
});
